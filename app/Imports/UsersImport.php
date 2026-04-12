<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class UsersImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithValidation,
    SkipsOnFailure
{
    use SkipsFailures;

    protected string $role;
    protected array $subjectCache = [];

    public function __construct(string $role)
    {
        $this->role = $role;
        
        // Cache all subjects at the beginning to avoid N+1 queries
        if ($role === 'teacher') {
            Subject::all()->each(function ($subject) {
                $this->subjectCache[strtolower(trim($subject->name))] = $subject->id;
            });
        }
    }

    public function collection(Collection $rows)
    {
        // Process all rows in one transaction for better performance
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->processRow($row);
            }
        });
    }

    protected function processRow($row)
    {
        // Debug: Log raw row data
        Log::info('Processing row for ' . $this->role, ['row' => $row]);
        
        if ($this->role === 'teacher') {

            $kodeUser = trim($row['id_pengajar'] ?? $row['kode_user'] ?? '');
            $name = trim($row['nama_pengajar'] ?? $row['nama'] ?? '');
            $password = (string) ($row['password_default'] ?? '123456');
            $subjectInput = trim($row['mapel'] ?? '');

        } else {

            $kodeUser = trim($row['id_siswa'] ?? $row['kode_user'] ?? '');
            $name = trim($row['nama_siswa'] ?? $row['nama'] ?? '');
            $password = (string) ($row['password_default'] ?? '123456');
            $subjectInput = null;
        }
        
        // Debug: Log extracted data
        Log::info('Extracted data', [
            'kode_user' => $kodeUser,
            'name' => $name,
            'role' => $this->role
        ]);

        if (!$kodeUser) {
            Log::warning('Skipping row: kode_user is empty');
            return null;
        }

        // Skip if user already exists (faster than validation)
        if (User::where('kode_user', $kodeUser)->exists()) {
            Log::info('Skipping row: user already exists', ['kode_user' => $kodeUser]);
            return null;
        }

        $user = User::create([
            'kode_user' => $kodeUser,
            'name'      => $name,
            'role'      => $this->role,
            'password'  => Hash::make($password),
        ]);
        
        Log::info('User created successfully', [
            'id' => $user->id,
            'kode_user' => $user->kode_user,
            'role' => $user->role
        ]);

        if ($this->role === 'student') {

            $user->student()->create([
                'year' => now()->year
            ]);
            
            Log::info('Student record created', ['user_id' => $user->id]);
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

            $user->teacher()->create([
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

    public function batchSize(): int
    {
        return 100;
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
