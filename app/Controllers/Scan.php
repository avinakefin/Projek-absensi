<?php

namespace App\Controllers;

use CodeIgniter\I18n\Time;
use App\Models\GuruModel;
use App\Models\SiswaModel;
use App\Models\PresensiGuruModel;
use App\Models\PresensiSiswaModel;
use App\Libraries\enums\TipeUser;

class Scan extends BaseController
{
    protected SiswaModel $siswaModel;
    protected GuruModel $guruModel;
    protected PresensiSiswaModel $presensiSiswaModel;
    protected PresensiGuruModel $presensiGuruModel;

    public function __construct()
    {
        $this->siswaModel = new SiswaModel();
        $this->guruModel = new GuruModel();
        $this->presensiSiswaModel = new PresensiSiswaModel();
        $this->presensiGuruModel = new PresensiGuruModel();
    }

    public function index($t = 'Masuk')
    {
        $data = ['waktu' => $t, 'title' => 'Absensi Siswa dan Guru Berbasis QR Code'];
        return view('scan/scan', $data);
    }

    public function cekKode()
    {
        // ambil variabel POST
        $uniqueCode = $this->request->getVar('unique_code');
        $waktuAbsen = $this->request->getVar('waktu');
        $isRFID = $this->request->getVar('isRFID') === 'true';

        $status = false;
        $type = TipeUser::Siswa;

        if ($isRFID) {
            // Handle RFID case
            if (strlen($uniqueCode) === 10) {
                $user = $this->siswaModel->where('NIS', $uniqueCode)->first();
                if ($user) {
                    $type = TipeUser::Siswa;
                    $status = $this->prosesAbsensi($user, $type, $waktuAbsen);
                } else {
                    $user = $this->guruModel->where('NUPTK', $uniqueCode)->first();
                    if ($user) {
                        $type = TipeUser::Guru;
                        $status = $this->prosesAbsensi($user, $type, $waktuAbsen);
                    }
                }
            }
        } else {
            // Handle QR Code case
            $user = $this->siswaModel->cekSiswa($uniqueCode);
            if (empty($user)) {
                $user = $this->guruModel->cekGuru($uniqueCode);
                if ($user) {
                    $type = TipeUser::Guru;
                    $status = $this->prosesAbsensi($user, $type, $waktuAbsen);
                }
            } else {
                $type = TipeUser::Siswa;
                $status = $this->prosesAbsensi($user, $type, $waktuAbsen);
            }
        }

        if (!$status) {
            return $this->showErrorView('Data tidak ditemukan');
        }
    }

    private function prosesAbsensi($result, $type, $waktuAbsen)
    {
        switch ($waktuAbsen) {
            case 'masuk':
                return $this->absenMasuk($type, $result);
            case 'pulang':
                return $this->absenPulang($type, $result);
            default:
                return false;
        }
    }

    public function absenMasuk($type, $result)
    {
        // data ditemukan
        $data['data'] = $result;
        $data['waktu'] = 'masuk';

        $date = Time::today()->toDateString();
        $time = Time::now()->toTimeString();

        switch ($type) {
            case TipeUser::Guru:
                $idGuru = $result['id_guru'];
                $data['type'] = TipeUser::Guru;
                $sudahAbsen = $this->presensiGuruModel->cekAbsen($idGuru, $date);
                if ($sudahAbsen) {
                    $data['presensi'] = $this->presensiGuruModel->getPresensiById($sudahAbsen);
                    return $this->showErrorView('Anda sudah absen hari ini', $data);
                }
                $this->presensiGuruModel->absenMasuk($idGuru, $date, $time);
                $data['presensi'] = $this->presensiGuruModel->getPresensiByIdGuruTanggal($idGuru, $date);

                // Ambil nomor HP dari tabel guru
                $guru = $this->guruModel->find($idGuru);
                if ($guru && isset($guru['no_hp'])) {
                    $this->sendWhatsAppNotification($result['nama_guru'], $guru['no_hp'], $date, $time);
                }

                return view('scan/scan-result', $data);

            case TipeUser::Siswa:
                $idSiswa = $result['id_siswa'];
                $idKelas = $result['id_kelas'];
                $data['type'] = TipeUser::Siswa;
                $sudahAbsen = $this->presensiSiswaModel->cekAbsen($idSiswa, Time::today()->toDateString());
                if ($sudahAbsen) {
                    $data['presensi'] = $this->presensiSiswaModel->getPresensiById($sudahAbsen);
                    return $this->showErrorView('Anda sudah absen hari ini', $data);
                }
                $this->presensiSiswaModel->absenMasuk($idSiswa, $date, $time, $idKelas);
                $data['presensi'] = $this->presensiSiswaModel->getPresensiByIdSiswaTanggal($idSiswa, $date);

                // Ambil nomor HP dari tabel siswa
                $siswa = $this->siswaModel->find($idSiswa);
                if ($siswa && isset($siswa['no_hp'])) {
                    $this->sendWhatsAppNotification($result['nama_siswa'], $siswa['no_hp'], $date, $time);
                }

                return view('scan/scan-result', $data);

            default:
                return $this->showErrorView('Tipe tidak valid');
        }
    }

    public function absenPulang($type, $result)
    {
        // data ditemukan
        $data['data'] = $result;
        $data['waktu'] = 'pulang';

        $date = Time::today()->toDateString();
        $time = Time::now()->toTimeString();

        switch ($type) {
            case TipeUser::Guru:
                $idGuru = $result['id_guru'];
                $data['type'] = TipeUser::Guru;
                $sudahAbsen = $this->presensiGuruModel->cekAbsen($idGuru, $date);
                if (!$sudahAbsen) {
                    return $this->showErrorView('Anda belum absen hari ini', $data);
                }
                $this->presensiGuruModel->absenKeluar($sudahAbsen, $time);
                $data['presensi'] = $this->presensiGuruModel->getPresensiById($sudahAbsen);

                // Ambil nomor HP dari tabel guru
                $guru = $this->guruModel->find($idGuru);
                if ($guru && isset($guru['no_hp'])) {
                    $this->sendWhatsAppNotification($result['nama_guru'], $guru['no_hp'], $date $time);
                }

                return view('scan/scan-result', $data);

            case TipeUser::Siswa:
                $idSiswa = $result['id_siswa'];
                $data['type'] = TipeUser::Siswa;
                $sudahAbsen = $this->presensiSiswaModel->cekAbsen($idSiswa, $date);
                if (!$sudahAbsen) {
                    return $this->showErrorView('Anda belum absen hari ini', $data);
                }
                $this->presensiSiswaModel->absenKeluar($sudahAbsen, $time);
                $data['presensi'] = $this->presensiSiswaModel->getPresensiById($sudahAbsen);

                // Ambil nomor HP dari tabel siswa
                $siswa = $this->siswaModel->find($idSiswa);
                if ($siswa && isset($siswa['no_hp'])) {
                    $this->sendWhatsAppNotification($result['nama_siswa'], $siswa['no_hp'], $date, $time);
                }

                return view('scan/scan-result', $data);

            default:
                return $this->showErrorView('Tipe tidak valid');
        }
    }

    public function showErrorView(string $msg = 'no error message', $data = NULL)
    {
        $errdata = $data ?? [];
        $errdata['msg'] = $msg;
        return view('scan/error-scan-result', $errdata);
    }

    private function sendWhatsAppNotification($name, $phoneNumber, $date, $time)
    {
        $message = "Saudara $name telah berhasil absen pada $date.\n\n**Waktu anda absen: $time**";
        // Kirim pesan ke nomor WhatsApp
        $url = "https://your-whatsapp-gateway-url/send-message"; //sesuaikan send-message dengan php wa gateway anda
        $data = [
            'api_key' => 'apikey yg dimiliki',
            'sender' => 'nomorhp pengirim',
            'number' => $phoneNumber,
            'message' => $message
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            log_message('error', 'Failed to send WhatsApp notification');
        }
    }
}
