<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UsersImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithValidation,
    SkipsOnFailure
{
    use SkipsFailures;

    protected string $role;
    protected array $subjectCache = [];
    protected int $totalRows = 0;
    protected int $validRows = 0;
    protected int $createdRows = 0;
    protected int $skippedRows = 0;
    protected array $passwordHashCache = [];

    public function __construct(string $role)
    {
        $this->role = $role;

        if ($role === 'teacher') {
            Subject::all()->each(function ($subject) {
                $this->subjectCache[strtolower(trim($subject->name))] = $subject->id;
            });
        }
    }

    public function collection(Collection $rows)
    {
        $this->totalRows += $rows->count();

        $codes = $rows->map(function ($row) {
            return trim((string) ($row[$this->role === 'teacher' ? 'id_pengajar' : 'id_siswa']
                ?? $row[$this->role === 'teacher' ? 'kode_pengajar' : 'kode_siswa']
                ?? $row[$this->role === 'teacher' ? 'id_tentor' : 'nis']
                ?? $row['kode_user'] ?? ''));
        })->filter()->unique()->values();

        $existingUsers = User::whereIn('kode_user', $codes)
            ->with($this->role === 'teacher' ? 'teacher' : 'student')
            ->get()
            ->keyBy('kode_user');

        DB::transaction(function () use ($rows, $existingUsers) {
            foreach ($rows as $row) {
                $this->processRow($row, $existingUsers);
            }
        });
    }

    protected function processRow($row, $existingUsers)
    {
        
        if ($this->role === 'teacher') {

            $kodeUser = trim((string) ($row['id_pengajar'] ?? $row['kode_pengajar'] ?? $row['id_tentor'] ?? $row['kode_user'] ?? ''));
            $name = trim((string) ($row['nama_pengajar'] ?? $row['nama_tentor'] ?? $row['nama_lengkap'] ?? $row['nama'] ?? ''));
            $password = trim((string) ($row['password_default'] ?? '')) ?: '123456';
            $subjectInput = trim((string) ($row['mapel'] ?? $row['mata_pelajaran'] ?? $row['subject'] ?? ''));

        } else {

            $kodeUser = trim((string) ($row['id_siswa'] ?? $row['kode_siswa'] ?? $row['nis'] ?? $row['kode_user'] ?? ''));
            $name = trim((string) ($row['nama_siswa'] ?? $row['nama_lengkap'] ?? $row['nama'] ?? ''));
            $password = trim((string) ($row['password_default'] ?? '')) ?: '123456';
            $subjectInput = null;
        }
        
        if (!$kodeUser || !$name) {
            $this->skippedRows++;
            return null;
        }

        $this->validRows++;

        $user = $existingUsers->get($kodeUser);

        if ($user && $user->role !== $this->role) {
            $this->skippedRows++;
            return null;
        }

        if ($user) {
            $profileExists = $this->role === 'teacher'
                ? $user->teacher()->exists()
                : $user->student()->exists();

            if ($profileExists) {
                $this->skippedRows++;
                return null;
            }
        }

        if (!$user) {
            $passwordHash = $this->passwordHashCache[$password]
                ??= Hash::make($password);

            $user = User::create([
                'kode_user' => $kodeUser,
                'name'      => $name,
                'role'      => $this->role,
                'password'  => $passwordHash,
            ]);
            $this->createdRows++;
        }

        if ($this->role === 'student') {

            $user->student()->firstOrCreate([], [
                'year' => now()->year
            ]);
            
        }

        if ($this->role === 'teacher') {

            $subjectId = null;

            if ($subjectInput) {

                $normalized = strtolower(trim($subjectInput));

                // Check from cache first
                if (isset($this->subjectCache[$normalized])) {
                    $subjectId = $this->subjectCache[$normalized];
                } else {
                    // Create new subject and add to cache
                    $subject = Subject::create([
                        'name' => $subjectInput
                    ]);
                    $this->subjectCache[$normalized] = $subject->id;
                    $subjectId = $subject->id;
                }
            }

            $user->teacher()->firstOrCreate([], [
                'teacher_code' => $kodeUser,
                'subject_id' => $subjectId
            ]);
        }

        return $user;
    }

    public function chunkSize(): int
    {
        return 100; // Process 100 rows per chunk
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function createdRows(): int
    {
        return $this->createdRows;
    }

    public function validRows(): int
    {
        return $this->validRows;
    }

    public function skippedRows(): int
    {
        return $this->skippedRows;
    }

    public function rules(): array
    {
        // Flexible validation - support multiple column formats
        if ($this->role === 'teacher') {
            return [
                '*.nama_pengajar' => 'sometimes|string|max:255',
                '*.nama' => 'sometimes|string|max:255',
                '*.password_default' => 'nullable|min:3',
            ];
        }

        return [
            '*.nama_siswa' => 'sometimes|string|max:255',
            '*.nama' => 'sometimes|string|max:255',
            '*.password_default' => 'nullable|min:3'
        ];
    }
}
