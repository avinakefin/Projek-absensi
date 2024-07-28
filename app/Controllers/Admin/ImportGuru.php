<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GuruModel;

class ImportGuru extends BaseController
{
    public function index()
    {
        $data['title'] = 'Import Guru';
        return view('admin/import_guru', $data);
    }

    public function import()
    {
        // Ambil data dari textarea
        $importData = $this->request->getPost('import_data');
        
        // Pisahkan baris data
        $rows = explode("\n", $importData);
        
        $guruModel = new GuruModel();
        $errorMessages = [];

        foreach ($rows as $row) {
            $columns = explode(' ', $row);
            
            if (count($columns) === 5) {
                $data = [
                    'nuptk' => trim($columns[0]),
                    'nama_guru' => trim($columns[1]),
                    'jenis_kelamin' => trim($columns[2]) === 'L' ? 'Laki-Laki' : 'Perempuan',
                    'alamat' => trim($columns[3]),
                    'no_hp' => trim($columns[4]),
                ];

                if (!$guruModel->insert($data)) {
                    $errorMessages[] = "Gagal mengimpor data guru: " . implode(',', $columns);
                }
            } else {
                $errorMessages[] = "Format data salah pada baris: " . $row;
            }
        }

        if (count($errorMessages) > 0) {
            return redirect()->back()->with('error', implode('<br>', $errorMessages));
        }

        return redirect()->to('admin/guru')->with('success', 'Data guru berhasil diimport');
    }
}
