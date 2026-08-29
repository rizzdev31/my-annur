<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SantriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataSantri = [

            // ==========================================
            // KELAS VII A
            // ==========================================
            [
                'nama_lengkap'  => 'Abi Kenzie Adinata',
                'nisn'          => '3138778465',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-08-14',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Alby Atharizz',
                'nisn'          => '0148539590',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-03-08',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Asyam Muchtar Tamamudin',
                'nisn'          => '63291845',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-06-01',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Daffa Ibnu Hafidz Susanto',
                'nisn'          => '0135624577',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-12-07',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Dharma Candra Putra',
                'nisn'          => '91827364',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-01-09',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fairuz Arjuna Chalief',
                'nisn'          => '0148432120',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-02-10',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Mohammad Hisyam Mahfud',
                'nisn'          => '3131374670',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-10-07',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhamad Az Dzakwan Bani Rachmadi',
                'nisn'          => '74819203',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-06-05',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Bintang Nararya',
                'nisn'          => '89124756',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-10-02',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Fakhri Al Faruq',
                'nisn'          => '0134016004',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-09-27',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Raja Hadi Al-Habsy',
                'nisn'          => '0134037678',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-02-12',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Reza Adilu Permana',
                'nisn'          => '3132275848',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-04-01',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Syarim Iffat Khoirullah',
                'nisn'          => '013179358',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-10-13',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Refdi Amir Syah Al Haq',
                'nisn'          => '54738291',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-01-19',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ridho Rizqullah Irmawan',
                'nisn'          => '0137564724',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-07-20',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ubay El Aliyu',
                'nisn'          => '3136085586',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-04-13',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Yusuf Sangga Nugraha',
                'nisn'          => '3147444651',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2014-01-08',
                'kode_kelas'    => 'VII A',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS VII B
            // ==========================================
            [
                'nama_lengkap'  => 'Adellia Ridha Naila',
                'nisn'          => '78392014',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-05-13',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Aisyah Afiqoh',
                'nisn'          => '82746193',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-09-21',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Amrina Rosyada',
                'nisn'          => '0138013215',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-10-05',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Aqilla Zahra Ratifa',
                'nisn'          => '0131884633',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-05-23',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Askana Nazhifa Syakhi',
                'nisn'          => '0144455697',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-04-01',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Gelsi Adiva Naira',
                'nisn'          => '0149013319',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-06-04',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Mikayla Nur Azalea',
                'nisn'          => '69182735',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-09-10',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nada Fajriah Salsabila',
                'nisn'          => '94837201',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-02-01',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Non Alifah Ramadhani',
                'nisn'          => '58291746',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-07-04',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Raisa Adila Irmawan',
                'nisn'          => '0146008237',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-05-12',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rania Azzahra Syawalya',
                'nisn'          => '71938462',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-08-22',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Sarah Afendi',
                'nisn'          => '84729103',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-12-14',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ufaira Nur Afifa',
                'nisn'          => '0146429662',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2014-02-13',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zara Nadia Akhyar',
                'nisn'          => '67382915',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-06-19',
                'kode_kelas'    => 'VII B',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS VIII A
            // ==========================================
            [
                'nama_lengkap'  => 'Abrima Dwi Pramuditiyo',
                'nisn'          => '0128781262',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-10-18',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Achmad Iqbal Nasrullah',
                'nisn'          => '3138482941',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-04-28',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ahnaf Lukman Ramadhan',
                'nisn'          => '3127815415',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-08-16',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Alvaro Akbar',
                'nisn'          => '0128556239',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-12-16',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Anugrah Karunia Agung',
                'nisn'          => '3129504859',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-11-28',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Carlen Shelloa Khoir',
                'nisn'          => '0125622344',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-12-10',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fakhri Mirza Azhfar',
                'nisn'          => '0128799007',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-08-29',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Felix Yusuf Al-Byan Santoso',
                'nisn'          => '0125890556',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-04-08',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Gadi Yuristanto',
                'nisn'          => '0134909207',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-06-03',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ganreff Ahmad Fisabil Kholik',
                'nisn'          => '3138310459',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-04-05',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Juniarta Wahyu Utomo',
                'nisn'          => '3128122676',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-06-27',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Kemal Narayan Mubarak',
                'nisn'          => '0137313895',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-06-28',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'M. Arifin Satya Sabilillah',
                'nisn'          => '3127184914',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-10-17',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Ashraf Al Akhlasahu',
                'nisn'          => '0131397867',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-05-30',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Azam Ibra Putra Khojin',
                'nisn'          => '0124267514',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-05-09',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Fariz Ahza Rizqullah',
                'nisn'          => '3127147209',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-11-13',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Naufal Afkar Riswanda',
                'nisn'          => '0124936042',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-11-04',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Raffi Rizqullah',
                'nisn'          => '3138045024',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-01-21',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Naufal Dhaifulloh',
                'nisn'          => '3122880904',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-07-10',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nazhirul Asrofi Sudarman',
                'nisn'          => '3139509429',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2013-03-16',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nizar Ahmad Fatih',
                'nisn'          => '3124330050',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-05-21',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rafael Nurandi',
                'nisn'          => '0127122752',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-09-11',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rizal Maulana Santoso',
                'nisn'          => '3127619578',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-02-12',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Royyan Fath Ramadhan',
                'nisn'          => '0127214739',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-08-18',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Yusron Rizqillah Haque',
                'nisn'          => '3122567767',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-10-23',
                'kode_kelas'    => 'VIII A',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS VIII B
            // ==========================================
            [
                'nama_lengkap'  => 'Aelita Fatimah Putri Imron',
                'nisn'          => '0128187227',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-08-14',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Afiqah Diana Putri Prayogo',
                'nisn'          => '3125389568',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-08-15',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ailsa Putri Nazihah',
                'nisn'          => '3129359940',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-08-28',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ainiya Assyabiyah Rafifa Hikari',
                'nisn'          => '0137853163',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-05-13',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ajwa Khairunnisa',
                'nisn'          => '0132061564',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-03-27',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fandysa Tsanilfa Al Khowarizmi',
                'nisn'          => '0128532248',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-09-05',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Felisha Ayudya Inara',
                'nisn'          => '0137288235',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-01-01',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Khansa Rayya Ramadhani',
                'nisn'          => '3129739249',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-08-05',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Khanza Naura Zahira',
                'nisn'          => '0121831862',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-10-30',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Mora Azkiya Kireina Nugraha',
                'nisn'          => '0135792258',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2013-02-27',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nanda Aulia Sanjaya',
                'nisn'          => '0127345687',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-10-11',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nur Khairunnisa Qaireen',
                'nisn'          => '3122699772',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-05-05',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Putri Nadia Az - Zahwa',
                'nisn'          => '3124775688',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-09-09',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ratu Aura Meyshia Iskandar',
                'nisn'          => '3120791049',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-05-18',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Sabbihisma Aqyane Agisa Nugraha',
                'nisn'          => '0124044574',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-08-23',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Shofiyanti',
                'nisn'          => '0122374749',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-04-14',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zahro Dwi Najmiyah Elyas',
                'nisn'          => '0129749297',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-11-22',
                'kode_kelas'    => 'VIII B',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS IX A
            // ==========================================
            [
                'nama_lengkap'  => 'Ahmad Firdaus Nuris Salam',
                'nisn'          => '3127949714',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-03-03',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Alfian Raysha Yudianto',
                'nisn'          => '0122377041',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-02-26',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Arkana Putra Athalla',
                'nisn'          => '0129297389',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-01-13',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Azzam Abdillah Tsany',
                'nisn'          => '0128933500',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-02-25',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fandi Muhammad Yusuf',
                'nisn'          => '0114409820',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-12-07',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Faraz Satria Abimanyu',
                'nisn'          => '0128015179',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-03-16',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Gavin Yuda Pratama',
                'nisn'          => '0115313841',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-12-10',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ghaisan Aqil Athallah',
                'nisn'          => '0115472709',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-11-06',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Haikal Aydin Tubagus',
                'nisn'          => '0119862265',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-07-26',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Hanif Mirza Hidayat',
                'nisn'          => '0125160238',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-04-30',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'M. ‘Azmi Mubaarak',
                'nisn'          => '0115591841',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-01-23',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Moch Ilyas Putra Nur Sya’ban',
                'nisn'          => '0111498099',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-07-18',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Mochammad Fathir Affandi',
                'nisn'          => '3110847177',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-05-29',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Abid Aqila Pranajha',
                'nisn'          => '3120591214',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-04-01',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Ammar Al Azwardin',
                'nisn'          => '0128954080',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-06-03',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Faaris Al Akbar',
                'nisn'          => '3126611233',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-04-26',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Faqih Nasiruddin',
                'nisn'          => '0119784102',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-06-13',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Kanz Atasyah Nugraha',
                'nisn'          => '0126984690',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-01-12',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Mursyid Bil Salji',
                'nisn'          => '0121232904',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-05-25',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Radja Boeby Lazuardi Saputra',
                'nisn'          => '0111993214',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-08-09',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rakha Bahtiar Ramadhan',
                'nisn'          => '0119976133',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-08-16',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rangga Ramadhani',
                'nisn'          => '3121318108',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2012-07-20',
                'kode_kelas'    => 'IX A',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS IX B
            // ==========================================
            [
                'nama_lengkap'  => 'Aira Rihhadatul \'Aisya',
                'nisn'          => '0115503978',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-06-11',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Anisa Mutiara Syafa',
                'nisn'          => '3126417892',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-03-21',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Anaya Makkayla Attya Firdausy S.',
                'nisn'          => '0118859437',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-12-26',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Aqilla Fadiyah Haya',
                'nisn'          => '3112818650',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-10-10',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Arlinda Nindy Safira',
                'nisn'          => '0122158560',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-07-03',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Azifah Laili Abidah',
                'nisn'          => '3118708776',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-11-11',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Azka Nur Hasanah',
                'nisn'          => '0118303966',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-09-26',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Binar Marsha Tiana Putri',
                'nisn'          => '0113263125',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-03-03',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Daffa Khaulah Azzahra',
                'nisn'          => '3096945533',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-04-10',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Daianty Saidah Ahmad',
                'nisn'          => '0128517142',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-06-26',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Erisa Dafiah Fitriani',
                'nisn'          => '0114045802',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-10-27',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fathimah Azzahra',
                'nisn'          => '0111036260',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-06-13',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Feriska Vina Qotrun Rosidy',
                'nisn'          => '0128490233',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-01-12',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fii Zilalil Dzakiyah Mahabbah',
                'nisn'          => '0115817753',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-11-30',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Hana Aila Varisha',
                'nisn'          => '3129145471',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-02-27',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Hana Laila Ramadhani',
                'nisn'          => '3119794526',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-08-27',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Kirana Mutiara Tazkya Adeira Vidi Laksana',
                'nisn'          => '3127486474',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-02-08',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Laras Carissa Maharani',
                'nisn'          => '0097193894',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-10-23',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Maghfirani El Farina',
                'nisn'          => '3116854197',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-11-14',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Meutia Indah Hersa',
                'nisn'          => '0129773071',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-05-11',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nurafifah Apriliana Rofi',
                'nisn'          => '0122907542',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-04-21',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Putri Velia Nadiro',
                'nisn'          => '3114093586',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-07-06',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Roudhotul Khasanah',
                'nisn'          => '3127833538',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-06-20',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Sabrina Firdausi Nuzula',
                'nisn'          => '3115179671',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-11-29',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Vaire Almas Zahirah',
                'nisn'          => '3125849699',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2012-04-20',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Yuke Livina Faza',
                'nisn'          => '3119311581',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-12-23',
                'kode_kelas'    => 'IX B',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS X
            // ==========================================
            [
                'nama_lengkap'  => 'Abdullah Nur Rochman Ramadhani',
                'nisn'          => '3110640961',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-08-24',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Daraka Naufal Sudarsono',
                'nisn'          => '0105721798',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-11-09',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Dhabit Azmi Al Fadhi',
                'nisn'          => '92847163',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-01-13',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Gangsar Pangestu',
                'nisn'          => '0114103696',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-04-18',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Hikmal Abror',
                'nisn'          => '0103365322',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-11-25',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Ilmi Hidayatulloh',
                'nisn'          => '0102955439',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-10-29',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rayhansyah Dhaffa Elgar Saputra',
                'nisn'          => '0119403680',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2011-02-08',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zulfan Fariq Ash Shiddiqi Mur\'am',
                'nisn'          => '0103657717',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-10-06',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Azahra Fadhila Kurnia',
                'nisn'          => '53918274',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2011-03-02',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Jinan Qothrun Nada',
                'nisn'          => '0103682176',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-05-10',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Madinatul Busro',
                'nisn'          => '76291840',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-03-21',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Raihaanah Nazarah Achmad',
                'nisn'          => '0103366567',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-11-21',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Rizki Raissa Rafaa',
                'nisn'          => '0105832561',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-12-27',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zur\'atun Nashiha',
                'nisn'          => '3108074692',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-05-12',
                'kode_kelas'    => 'X',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS XI
            // ==========================================
            [
                'nama_lengkap'  => 'Azzam Nafis Azhary',
                'nisn'          => '0101286409',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-03-04',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Daffa Arif Setiawan',
                'nisn'          => '0107173052',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-02-12',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Radys Alief Ridwansyah',
                'nisn'          => '0104484449',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-03-15',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Sa\'if Zuhdi Al-Farobi',
                'nisn'          => '83917264',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-07-09',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Waris Ilyas Al Ayyuby',
                'nisn'          => '64829173',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-11-17',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zidni Alfian Barik',
                'nisn'          => '0093222764',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2010-03-28',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Aqidatul Izzah Aslikha',
                'nisn'          => '97182945',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-04-27',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Azizah Khansa Kurnia',
                'nisn'          => '59283741',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-11-26',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Cynara Azaria Ramadhani Hidayat',
                'nisn'          => '0097802703',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-08-27',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Fitri Azzahra',
                'nisn'          => '0098829760',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-11-19',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Naqila Keysha Electra',
                'nisn'          => '0098791332',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-04-03',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nasywa Ashilah Ramadhani',
                'nisn'          => '0095395160',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-08-30',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Natasya Choirun Nisa\'',
                'nisn'          => '3098075629',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-10-01',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nayyara Al Fayyaza',
                'nisn'          => '3102758517',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-07-19',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ratu Dalila Kamal',
                'nisn'          => '0104351784',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-08-04',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Salwa Gandes Gandarina',
                'nisn'          => '0102263445',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2010-08-27',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Shafa Nur Adzilli',
                'nisn'          => '0096473215',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-11-17',
                'kode_kelas'    => 'XI',
                'tahun_ajaran'  => null,
            ],

            // ==========================================
            // KELAS XII
            // ==========================================
            [
                'nama_lengkap'  => 'Achmad Maulana Rabbany',
                'nisn'          => '3082801937',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2008-10-15',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Ershanda Assyfa Putra Ramadhan',
                'nisn'          => '0098710990',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-08-28',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muchammad Izzah Farabi',
                'nisn'          => '0088390567',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2008-12-29',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Daffa Ikmalul Farhan',
                'nisn'          => '0096045253',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-02-04',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Muhammad Zidan',
                'nisn'          => '0096523557',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-06-03',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nabil Rafi Rabbani',
                'nisn'          => '0099505942',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-01-20',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Naufal Aldzakwan Riftansyah',
                'nisn'          => '0092003283',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-05-02',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Satriyo Alif Putra Muraindra',
                'nisn'          => '0097541808',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-05-18',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Zulmi Ikhwan Ma\'rufi',
                'nisn'          => '0097161191',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2009-01-13',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Alifah Nicole Babby Supriyanto',
                'nisn'          => '99329793',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-02-24',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Farah Hariro',
                'nisn'          => '3096705485',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-07-07',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Hananda Shafiqah Zulvanie El Varetta',
                'nisn'          => '0083818630',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2008-08-20',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Nadya Nur Farikhah',
                'nisn'          => '0093550456',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2009-05-07',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Sherlanicha Auliakauri',
                'nisn'          => '0087557886',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2008-09-29',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
            [
                'nama_lengkap'  => 'Syfaretta Lingga Anggie Nugraha',
                'nisn'          => '0088921169',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2008-09-08',
                'kode_kelas'    => 'XII',
                'tahun_ajaran'  => null,
            ],
        ];

        DB::transaction(function () use ($dataSantri) {
            // 1) Ambil atau Buat Tahun Ajaran Aktif
            $activeTahunAjaranId = DB::table('tahun_ajaran')->where('is_aktif', true)->value('id');

            if (!$activeTahunAjaranId) {
                $defaultNamaTahun = '2025/2026';
                $existingTahun = DB::table('tahun_ajaran')
                    ->where('nama', $defaultNamaTahun)
                    ->where('semester', 'ganjil')
                    ->first();

                if ($existingTahun) {
                    $activeTahunAjaranId = $existingTahun->id;
                    DB::table('tahun_ajaran')->where('id', $activeTahunAjaranId)->update(['is_aktif' => true]);
                } else {
                    $activeTahunAjaranId = DB::table('tahun_ajaran')->insertGetId([
                        'nama'            => $defaultNamaTahun,
                        'semester'        => 'ganjil',
                        'tanggal_mulai'   => '2025-07-15',
                        'tanggal_selesai' => '2025-12-20',
                        'is_aktif'        => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            // Cache Tahun Ajaran dan Kelas untuk efisiensi transaksi
            $tahunAjaranCache = [];
            $kelasCache       = [];
            $defaultTanggalMasuk = now()->toDateString();

            foreach ($dataSantri as $item) {
                $namaLengkap = trim($item['nama_lengkap']);
                $nip         = trim((string) $item['nisn']); // disimpan ke kolom nip di tabel santri
                $jk          = $this->normalJK($item['jenis_kelamin'] ?? null);
                $tglLahir    = $this->normalTgl($item['tanggal_lahir'] ?? null);
                $kodeKelas   = trim(preg_replace('/\s+/', ' ', strtoupper($item['kode_kelas'] ?? '')));
                $customTahun = !empty($item['tahun_ajaran']) ? trim($item['tahun_ajaran']) : null;

                // Tentukan tahun_ajaran_id (opsional dari data, default pakai tahun ajaran aktif)
                if ($customTahun) {
                    if (!isset($tahunAjaranCache[$customTahun])) {
                        $tId = DB::table('tahun_ajaran')->where('nama', $customTahun)->value('id');
                        if (!$tId) {
                            $tId = DB::table('tahun_ajaran')->insertGetId([
                                'nama'            => $customTahun,
                                'semester'        => 'ganjil',
                                'tanggal_mulai'   => '2025-07-15',
                                'tanggal_selesai' => '2025-12-20',
                                'is_aktif'        => true,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }
                        $tahunAjaranCache[$customTahun] = $tId;
                    }
                    $currentTahunAjaranId = $tahunAjaranCache[$customTahun];
                } else {
                    $currentTahunAjaranId = $activeTahunAjaranId;
                }

                // 2) Kelas: firstOrCreate by (nama, tahun_ajaran_id)
                $kelasKey = "{$kodeKelas}_{$currentTahunAjaranId}";
                if (!isset($kelasCache[$kelasKey])) {
                    $kId = DB::table('kelas')
                        ->where('nama', $kodeKelas)
                        ->where('tahun_ajaran_id', $currentTahunAjaranId)
                        ->value('id');

                    if (!$kId) {
                        $kId = DB::table('kelas')->insertGetId([
                            'nama'            => $kodeKelas,
                            'jenis'           => 'sekolah',
                            'tingkat'         => $this->tingkatDariKode($kodeKelas),
                            'tahun_ajaran_id' => $currentTahunAjaranId,
                            'is_aktif'        => true,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }
                    $kelasCache[$kelasKey] = $kId;
                }
                $kelasId = $kelasCache[$kelasKey];

                // 3) Santri: updateOrCreate by (nip)
                $existingSantri = DB::table('santri')->where('nip', $nip)->first();

                if ($existingSantri) {
                    DB::table('santri')->where('nip', $nip)->update([
                        'nama_lengkap'  => $namaLengkap,
                        'jenis_kelamin' => $jk,
                        'tanggal_lahir' => $tglLahir,
                        'is_aktif'      => true,
                        'updated_at'    => now(),
                    ]);
                    $santriId = $existingSantri->id;
                } else {
                    $santriId = DB::table('santri')->insertGetId([
                        'nip'           => $nip,
                        'nama_lengkap'  => $namaLengkap,
                        'jenis_kelamin' => $jk,
                        'tanggal_lahir' => $tglLahir,
                        'is_aktif'      => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                // 4) Kelas_Santri: updateOrCreate by (santri_id, tahun_ajaran_id)
                $existingPivot = DB::table('kelas_santri')
                    ->where('santri_id', $santriId)
                    ->where('tahun_ajaran_id', $currentTahunAjaranId)
                    ->first();

                if ($existingPivot) {
                    DB::table('kelas_santri')
                        ->where('id', $existingPivot->id)
                        ->update([
                            'kelas_id'      => $kelasId,
                            'tanggal_masuk' => $existingPivot->tanggal_masuk ?? $defaultTanggalMasuk,
                            'is_aktif'      => true,
                            'updated_at'    => now(),
                        ]);
                } else {
                    DB::table('kelas_santri')->insert([
                        'kelas_id'        => $kelasId,
                        'santri_id'       => $santriId,
                        'tahun_ajaran_id' => $currentTahunAjaranId,
                        'tanggal_masuk'   => $defaultTanggalMasuk,
                        'is_aktif'        => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Helper: Mendapatkan tingkat numerik dari kode kelas Romawi
     * (VII=7, VIII=8, IX=9, X=10, XI=11, XII=12)
     */
    private function tingkatDariKode(string $kode): int
    {
        $kode = trim(strtoupper($kode));

        if (preg_match('/^(XII|XI|IX|X|VIII|VII|VI|IV|V|III|II|I)\b/i', $kode, $m)) {
            $roman = strtoupper($m[1]);
            $map = [
                'I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5,
                'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10,
                'XI' => 11, 'XII' => 12
            ];
            return $map[$roman] ?? 7;
        }

        $map = [
            'XII' => 12, 'XI' => 11, 'X' => 10, 'IX' => 9, 'VIII' => 8,
            'VII' => 7, 'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3,
            'II' => 2, 'I' => 1
        ];
        foreach ($map as $r => $val) {
            if (str_starts_with($kode, $r)) {
                return $val;
            }
        }

        if (preg_match('/\d+/', $kode, $m)) {
            return (int) $m[0];
        }

        return 7;
    }

    /**
     * Helper: Normalisasi jenis kelamin menjadi 'L' atau 'P'
     */
    private function normalJK(?string $v): string
    {
        if (empty($v)) {
            return 'L';
        }

        $v = trim(strtoupper($v));
        if (in_array($v, ['P', 'PEREMPUAN', 'WANITA', 'FEMALE', 'F'])) {
            return 'P';
        }

        return 'L';
    }

    /**
     * Helper: Normalisasi tanggal berbagai format ke 'YYYY-MM-DD'
     */
    private function normalTgl(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        $raw = preg_replace('/^(minggu|senin|selasa|rabu|kamis|jumat|sabtu|sunday|monday|tuesday|wednesday|thursday|friday|saturday),\s*/i', '', $raw);

        $months = [
            'januari' => '01', 'january' => '01', 'jan' => '01',
            'februari' => '02', 'pebruari' => '02', 'february' => '02', 'feb' => '02',
            'maret' => '03', 'march' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04',
            'mei' => '05', 'may' => '05',
            'juni' => '06', 'june' => '06', 'jun' => '06',
            'juli' => '07', 'july' => '07', 'jul' => '07',
            'agustus' => '08', 'august' => '08', 'agt' => '08', 'aug' => '08',
            'september' => '09', 'sept' => '09', 'sep' => '09',
            'oktober' => '10', 'october' => '10', 'okt' => '10', 'oct' => '10',
            'nopember' => '11', 'november' => '11', 'nop' => '11', 'nov' => '11',
            'desember' => '12', 'december' => '12', 'des' => '12', 'dec' => '12',
        ];

        if (preg_match('/^([a-z]+)\s+(\d{1,2}),?\s+(\d{4})$/i', $raw, $m)) {
            $mName = strtolower($m[1]);
            $month = $months[$mName] ?? '01';
            $day   = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year  = $m[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/i', $raw, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mName = strtolower($m[2]);
            $month = $months[$mName] ?? '01';
            $year  = $m[3];
            if ((int)$year < 1900 && (int)$year > 1000) {
                $year = '20' . substr($year, -2);
            }
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }
}
