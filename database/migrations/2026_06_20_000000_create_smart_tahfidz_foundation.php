<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Tahfidz — Fase 4A: Fondasi Data
 * - mata_pelajaran.tipe (reguler|tahfidz|tahsin)  → penentu form jurnal
 * - santri.tahsin_level (1..5, nullable)
 * - surah  : referensi 114 surah + jumlah ayat (patokan total 6236 ayat)
 * - juz    : 30 penanda awal juz (surah:ayat) → deteksi juz otomatis
 * Semua ADITIF; tidak mengubah tabel/payroll yang berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->enum('tipe', ['reguler', 'tahfidz', 'tahsin'])->default('reguler')->after('kategori');
        });

        Schema::table('santri', function (Blueprint $table) {
            $table->unsignedTinyInteger('tahsin_level')->nullable()->after('is_aktif')
                  ->comment('Level tahsin santri (1..5)');
        });

        Schema::create('surah', function (Blueprint $table) {
            $table->unsignedSmallInteger('nomor')->primary();
            $table->string('nama');
            $table->unsignedSmallInteger('jumlah_ayat');
        });

        Schema::create('juz', function (Blueprint $table) {
            $table->unsignedTinyInteger('nomor')->primary();
            $table->unsignedSmallInteger('mulai_surah'); // nomor surah awal juz
            $table->unsignedSmallInteger('mulai_ayat');  // ayat awal juz
        });

        // ── Seed 114 surah (nomor, nama, jumlah_ayat) — total 6236 ayat ────────
        $surah = [
            [1,'Al-Fatihah',7],[2,'Al-Baqarah',286],[3,"Ali 'Imran",200],[4,"An-Nisa'",176],
            [5,"Al-Ma'idah",120],[6,"Al-An'am",165],[7,"Al-A'raf",206],[8,'Al-Anfal',75],
            [9,'At-Taubah',129],[10,'Yunus',109],[11,'Hud',123],[12,'Yusuf',111],
            [13,"Ar-Ra'd",43],[14,'Ibrahim',52],[15,'Al-Hijr',99],[16,'An-Nahl',128],
            [17,"Al-Isra'",111],[18,'Al-Kahf',110],[19,'Maryam',98],[20,'Ta-Ha',135],
            [21,"Al-Anbiya'",112],[22,'Al-Hajj',78],[23,"Al-Mu'minun",118],[24,'An-Nur',64],
            [25,'Al-Furqan',77],[26,"Asy-Syu'ara'",227],[27,'An-Naml',93],[28,'Al-Qasas',88],
            [29,"Al-'Ankabut",69],[30,'Ar-Rum',60],[31,'Luqman',34],[32,'As-Sajdah',30],
            [33,'Al-Ahzab',73],[34,"Saba'",54],[35,'Fatir',45],[36,'Ya-Sin',83],
            [37,'As-Saffat',182],[38,'Sad',88],[39,'Az-Zumar',75],[40,'Gafir',85],
            [41,'Fussilat',54],[42,'Asy-Syura',53],[43,'Az-Zukhruf',89],[44,'Ad-Dukhan',59],
            [45,'Al-Jasiyah',37],[46,'Al-Ahqaf',35],[47,'Muhammad',38],[48,'Al-Fath',29],
            [49,'Al-Hujurat',18],[50,'Qaf',45],[51,'Az-Zariyat',60],[52,'At-Tur',49],
            [53,'An-Najm',62],[54,'Al-Qamar',55],[55,'Ar-Rahman',78],[56,"Al-Waqi'ah",96],
            [57,'Al-Hadid',29],[58,'Al-Mujadilah',22],[59,'Al-Hasyr',24],[60,'Al-Mumtahanah',13],
            [61,'As-Saff',14],[62,"Al-Jumu'ah",11],[63,'Al-Munafiqun',11],[64,'At-Tagabun',18],
            [65,'At-Talaq',12],[66,'At-Tahrim',12],[67,'Al-Mulk',30],[68,'Al-Qalam',52],
            [69,'Al-Haqqah',52],[70,"Al-Ma'arij",44],[71,'Nuh',28],[72,'Al-Jinn',28],
            [73,'Al-Muzzammil',20],[74,'Al-Muddassir',56],[75,'Al-Qiyamah',40],[76,'Al-Insan',31],
            [77,'Al-Mursalat',50],[78,"An-Naba'",40],[79,"An-Nazi'at",46],[80,'Abasa',42],
            [81,'At-Takwir',29],[82,'Al-Infitar',19],[83,'Al-Mutaffifin',36],[84,'Al-Insyiqaq',25],
            [85,'Al-Buruj',22],[86,'At-Tariq',17],[87,"Al-A'la",19],[88,'Al-Gasyiyah',26],
            [89,'Al-Fajr',30],[90,'Al-Balad',20],[91,'Asy-Syams',15],[92,'Al-Lail',21],
            [93,'Ad-Duha',11],[94,'Asy-Syarh',8],[95,'At-Tin',8],[96,"Al-'Alaq",19],
            [97,'Al-Qadr',5],[98,'Al-Bayyinah',8],[99,'Az-Zalzalah',8],[100,"Al-'Adiyat",11],
            [101,"Al-Qari'ah",11],[102,'At-Takasur',8],[103,"Al-'Asr",3],[104,'Al-Humazah',9],
            [105,'Al-Fil',5],[106,'Quraisy',4],[107,"Al-Ma'un",7],[108,'Al-Kausar',3],
            [109,'Al-Kafirun',6],[110,'An-Nasr',3],[111,'Al-Masad',5],[112,'Al-Ikhlas',4],
            [113,'Al-Falaq',5],[114,'An-Nas',6],
        ];
        DB::table('surah')->insert(array_map(
            fn($s) => ['nomor' => $s[0], 'nama' => $s[1], 'jumlah_ayat' => $s[2]], $surah
        ));

        // ── Seed 30 penanda awal juz (juz, surah, ayat) ───────────────────────
        $juz = [
            [1,1,1],[2,2,142],[3,2,253],[4,3,93],[5,4,24],[6,4,148],[7,5,82],[8,6,111],
            [9,7,88],[10,8,41],[11,9,93],[12,11,6],[13,12,53],[14,15,1],[15,17,1],[16,18,75],
            [17,21,1],[18,23,1],[19,25,21],[20,27,56],[21,29,46],[22,33,31],[23,36,28],
            [24,39,32],[25,41,47],[26,46,1],[27,51,31],[28,58,1],[29,67,1],[30,78,1],
        ];
        DB::table('juz')->insert(array_map(
            fn($j) => ['nomor' => $j[0], 'mulai_surah' => $j[1], 'mulai_ayat' => $j[2]], $juz
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('juz');
        Schema::dropIfExists('surah');
        Schema::table('santri', function (Blueprint $table) {
            $table->dropColumn('tahsin_level');
        });
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
