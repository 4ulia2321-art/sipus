@php
    $user = auth()->user();
    $nama = $user->username ?? 'Pengguna';
    $peran = in_array($user->role ?? '', ['Admin', 'Pustakawan']) ? 'Pustakawan' : 'Siswa';
@endphp
<a href="{{ route('profil') }}" class="profile-card mobile-only">
    <i class="bi bi-person-circle avatar"></i>
    <div>
        <strong>{{ $nama }}</strong>
        <span>{{ $peran }}</span>
    </div>
    <i class="bi bi-chevron-right"></i>
</a>
