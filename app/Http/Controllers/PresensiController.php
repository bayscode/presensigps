<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PresensiController extends Controller
{
    public function create()
    {
        /**
         * Cek apakah karyawan tersebut sudah melakukan absensi hari ini?
         * kalau sudah, maka dia akan bernilai 1.
         */
        $hari = date("Y-m-d");
        $nik = Auth::guard('karyawan')->user()->nik;
        $cek = DB::table('presensi')->where('tgl_presensi', $hari)->where('nik', $nik)->count();
        return view('presensi.create', compact('cek'));
    }

    public function store(Request $request)
    {
        $nik = Auth::guard('karyawan')->user()->nik;
        $tgl_presensi = date("Y-m-d");
        $jam = date("H:i:s");
        // -2.943855117084306, 104.78319361765587
        $latitudekantor = -2.943855117084306;
        $longitudekantor = 104.78319361765587;
        $lokasi = $request->lokasi;
        $lokasiuser = explode(",", $lokasi);
        $latitudeuser = $lokasiuser[0];
        $longitudeuser = $lokasiuser[1];

        $jarak = $this->distance($latitudekantor, $longitudekantor, $latitudeuser, $longitudeuser);
        $radius = round($jarak["meters"]); // Menampilkan total jarak
        $image = $request->image;
        // Validasi

        $folderPath = "public/uploads/absensi/";
        $formatName = $nik . "-" . $tgl_presensi;
        $image_parts = explode(";base64", $image);
        $image_base64 = base64_decode($image_parts[1]);
        $fileName = $formatName . ".png";
        $file = $folderPath . $fileName;

        /**
         * Jadi kalau misalnya karyawan tersebut sudah melakukan absen, maka perintah
         * yang dilakukan/dijalankan bukan menyimpan lagi, tapi untuk mengupdate
         * datanya.
         */
        $cek = DB::table('presensi')->where('tgl_presensi', $tgl_presensi)->where('nik', $nik)->count();
        /**
         * Jadi kalau berada didalam radius baru melakukan pengecekan, apakah dia sudah melakukan
         * absen atau belum, kalau belum berarti simpan data, kalau sudah berarti
         * update data.
         */
        if ($radius > 10) {
            echo "error|Maaf anda berada diluar jangkauan, jarak anda " . $radius . " meter dari kantor|radius";
        } else {
            if ($cek > 0) {
                $data_pulang = [
                    'jam_out' => $jam,
                    'foto_out' => $fileName,
                    'lokasi_out' => $lokasi
                ];
                $update = DB::table('presensi')->where('tgl_presensi', $tgl_presensi)->where('nik', $nik)->update($data_pulang);

                if ($update) {
                    echo "success|Terima kasih, Atas kerja kerasnya!|out";
                    Storage::put($file, $image_base64);
                } else {
                    echo "error|Maaf anda gagal untuk absen!|out";
                }
                /**
                 * Kalau udah absen maka update data pulangnya, kalau belum absen
                 * maka insert data masuknya.
                 */
            } else {
                $data = [
                    'nik' => $nik,
                    'tgl_presensi' => $tgl_presensi,
                    'jam_in' => $jam,
                    'foto_in' => $fileName,
                    'lokasi_in' => $lokasi
                ];

                $simpan = DB::table('presensi')->insert($data);
                if ($simpan) {
                    echo "success|Terima kasih, Semangat kerjanya!|in";
                    Storage::put($file, $image_base64);
                } else {
                    echo "error|Maaf anda gagal untuk absen!|in";
                }
            }
        }
    }

    //Menghitung Jarak
    function distance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $miles = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
        $miles = acos($miles);
        $miles = rad2deg($miles);
        $miles = $miles * 60 * 1.1515;
        $feet = $miles * 5280;
        $yards = $feet / 3;
        $kilometers = $miles * 1.609344;
        $meters = $kilometers * 1000;
        return compact('meters');
    }
}