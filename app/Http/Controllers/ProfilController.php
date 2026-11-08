<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->role !== 'Siswa') {
            $siswa = DB::table('siswa')->where('id_user', $user->id_user)->first();
            $jumlahPeminjaman = $siswa
                ? DB::table('peminjaman')->where('id_siswa', $siswa->id_siswa)->count()
                : null;

            return view('admin.profil.index', compact('user', 'siswa', 'jumlahPeminjaman'));
        }

        $row = DB::table('siswa')->where('id_user', $user->id_user)->first();

        $siswa = (object) [
            'nama'          => $row->nama_siswa ?? $user->username,
            'nis'           => $row->nis ?? '-',
            'kelas'         => $row->kelas ?? '-',
            'alamat'        => $row->alamat ?? null,
            'no_hp'         => $row->no_telp ?? null,
            'email'         => $user->username,
            'foto'          => null,
            'tahun_ajaran'  => null,
            'jenis_kelamin' => $row->jenis_kelamin ?? null,
            'tempat_lahir'  => $row->tempat_lahir ?? null,
            'tanggal_lahir' => $row->tanggal_lahir ?? null,
        ];

        $jumlahPeminjaman = $row
            ? DB::table('peminjaman')->where('id_siswa', $row->id_siswa)->count()
            : 0;

        $sedangDipinjam = $row
            ? DB::table('peminjaman as p')
                ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'p.id_peminjaman')
                ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                ->leftJoin('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
                ->where('p.id_siswa', $row->id_siswa)
                ->where('p.status', 'Dipinjam')
                ->whereNull('pg.id_pengembalian')
                ->orderByDesc('p.tanggal_pinjam')
                ->select('b.judul', 'p.tanggal_pinjam', 'p.tanggal_jatuh_tempo')
                ->get()
                ->map(fn ($p) => (object) [
                    'buku'           => (object) ['judul' => $p->judul],
                    'tanggal_pinjam' => Carbon::parse($p->tanggal_pinjam),
                    'jatuh_tempo'    => Carbon::parse($p->tanggal_jatuh_tempo),
                ])
            : collect();

        $riwayat = $row
            ? DB::table('peminjaman as p')
                ->join('detail_peminjaman as dp', 'dp.id_peminjaman', '=', 'p.id_peminjaman')
                ->join('buku as b', 'b.id_buku', '=', 'dp.id_buku')
                ->join('pengembalian as pg', 'pg.id_peminjaman', '=', 'p.id_peminjaman')
                ->where('p.id_siswa', $row->id_siswa)
                ->orderByDesc('pg.tanggal_kembali')
                ->limit(5)
                ->select('b.judul', 'p.tanggal_pinjam', 'pg.tanggal_kembali')
                ->get()
                ->map(fn ($p) => (object) [
                    'buku'            => (object) ['judul' => $p->judul],
                    'tanggal_pinjam'  => Carbon::parse($p->tanggal_pinjam),
                    'tanggal_kembali' => $p->tanggal_kembali ? Carbon::parse($p->tanggal_kembali) : null,
                ])
            : collect();

        return view('profil.index', compact(
            'user',
            'siswa',
            'jumlahPeminjaman',
            'sedangDipinjam',
            'riwayat'
        ));
    }

    public function edit()
    {
        $user = auth()->user();

        $siswa = $user->role === 'Siswa'
            ? DB::table('siswa')->where('id_user', $user->id_user)->first()
            : null;

        return view('profil.edit', compact('user', 'siswa'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $siswa = $user->role === 'Siswa'
            ? DB::table('siswa')->where('id_user', $user->id_user)->first()
            : null;

        $data = $request->validate([
            'name'       => ['nullable', 'string', 'max:100'],
            'username'   => ['required', 'string', 'max:100', Rule::unique('t_user', 'username')->ignore($user->id_user, 'id_user')],
            'password'   => ['nullable', 'string', 'min:6'],
            'nis'        => [Rule::requiredIf($user->role === 'Siswa'), 'nullable', 'string', 'max:20', Rule::unique('siswa', 'nis')->ignore($siswa->id_siswa ?? null, 'id_siswa')],
            'nama_siswa' => [Rule::requiredIf($user->role === 'Siswa'), 'nullable', 'string', 'max:100'],
            'kelas'          => [Rule::requiredIf($user->role === 'Siswa'), 'nullable', 'string', 'max:30'],
            'jenis_kelamin'  => ['nullable', 'in:Laki-laki,Perempuan'],
            'tempat_lahir'   => ['nullable', 'string', 'max:100'],
            'tanggal_lahir'  => ['nullable', 'date'],
            'alamat'         => ['nullable', 'string'],
            'no_telp'        => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($data, $user, $siswa) {
            $userUpdate = ['username' => $data['username']];

            // Hanya Admin/Pustakawan yang punya nama tampilan di t_user.
            // Nama siswa dibaca dari tabel siswa yang terhubung ke akun.
            if ($user->role !== 'Siswa') {
                $userUpdate['name'] = $data['name'] ?? null;
            }

            DB::table('t_user')->where('id_user', $user->id_user)->update($userUpdate);

            if (! empty($data['password'])) {
                DB::table('t_user')->where('id_user', $user->id_user)->update([
                    'password' => Hash::make($data['password']),
                ]);
            }

            if ($user->role === 'Siswa') {
                $payload = [
                    'nis'           => $data['nis'],
                    'nama_siswa'    => $data['nama_siswa'],
                    'kelas'         => $data['kelas'],
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                    'tempat_lahir'  => $data['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                    'alamat'        => $data['alamat'] ?? null,
                    'no_telp'       => $data['no_telp'] ?? null,
                ];

                if ($siswa) {
                    DB::table('siswa')->where('id_siswa', $siswa->id_siswa)->update($payload);
                } else {
                    $payload['id_user'] = $user->id_user;
                    DB::table('siswa')->insert($payload);
                }
            }
        });

        return redirect()->route('profil')->with('success', 'Profil berhasil diperbarui.');
    }

    public function tentang()
    {
        return view('admin.tentang');
    }
}
