<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Imports\UsersImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class UserController extends Controller
{
    public function updateProfileAvatar(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => 'required|string|regex:/^avatars\/[1-6]\.png$/',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Profile avatar updated successfully',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Get all users with role filtering
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            $users = $query->latest()->paginate(10);

            return response()->json([
                'message' => 'User list retrieved successfully',
                'data' => $users,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'kode_user' => 'required|string|max:50|unique:users,kode_user',
                'name' => 'required|string|max:255',
                'password' => 'required|min:6',
                'role' => 'required|in:admin,teacher,student',
            ]);

            $user = User::create([
                'kode_user' => $request->kode_user,
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create user. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'message' => 'User details retrieved',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => 'The requested user does not exist',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error retrieving user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user information
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'kode_user' => 'required|string|max:50|unique:users,kode_user,' . $id,
                'name' => 'required|string|max:255',
                'role' => 'required|in:admin,teacher,student',
                'profile_photo' => 'nullable|string|regex:/^avatars\/[1-6]\.png$/',
            ]);

            $user->update([
                'kode_user' => $request->kode_user,
                'name' => $request->name,
                'role' => $request->role,
                'profile_photo' => $request->profile_photo,
            ]);

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => 'The user you are trying to update does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update user. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'password' => 'required|min:6',
            ]);

            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'message' => 'Password updated successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => 'The user does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => 'The user you are trying to delete does not exist',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete user',
                'error' => 'This user may have related data. Please remove related data first.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import teachers from Excel file
     */
    public function importTeachers(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $import = new UsersImport('teacher');
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();

            if ($import->totalRows() === 0 || $import->validRows() === 0) {
                return response()->json([
                    'message' => 'Tidak ada baris tentor yang berhasil diproses. Periksa header Excel dan pastikan ID belum digunakan untuk role lain.',
                    'total_rows' => $import->totalRows(),
                    'valid_rows' => $import->validRows(),
                    'created_rows' => $import->createdRows(),
                    'skipped_rows' => $import->skippedRows(),
                    'failures' => $failures,
                ], 422);
            }

            return response()->json([
                'message' => 'Teachers imported successfully',
                'total_rows' => $import->totalRows(),
                'valid_rows' => $import->validRows(),
                'created_rows' => $import->createdRows(),
                'skipped_rows' => $import->skippedRows(),
                'failures' => $failures,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import students from Excel file
     */
    public function importStudents(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $import = new UsersImport('student');
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();

            if ($import->totalRows() === 0 || $import->validRows() === 0) {
                return response()->json([
                    'message' => 'Tidak ada baris pelajar yang berhasil diproses. Periksa header Excel dan pastikan ID belum digunakan untuk role lain.',
                    'total_rows' => $import->totalRows(),
                    'valid_rows' => $import->validRows(),
                    'created_rows' => $import->createdRows(),
                    'skipped_rows' => $import->skippedRows(),
                    'failures' => $failures,
                ], 422);
            }

            return response()->json([
                'message' => 'Students imported successfully',
                'total_rows' => $import->totalRows(),
                'valid_rows' => $import->validRows(),
                'created_rows' => $import->createdRows(),
                'skipped_rows' => $import->skippedRows(),
                'failures' => $failures,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
    

