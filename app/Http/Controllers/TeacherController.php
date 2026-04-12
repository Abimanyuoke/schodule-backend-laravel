<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class TeacherController extends Controller
{
    /**
     * Get all teachers with user and subject relation
     */
    public function index(Request $request)
    {
        try {
            // $query = Teacher::query();
            $query = Teacher::with(['user', 'subject']);
            $perPage = max(1, min((int) $request->query('per_page', 10), 500));

            if ($request->filled('subject_id')) {
                $query->where('subject_id', $request->subject_id);
                
            }

            $teachers = $query->latest()->paginate(10);
            $teachers = $query->latest()->paginate($perPage);

            return response()->json([
                'message' => 'Teacher list retrieved successfully',
                'data' => $teachers,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve teachers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new teacher profile
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id|unique:teachers,user_id',
                'teacher_code' => 'nullable|string|max:100',
                'subject_id' => 'nullable|exists:subjects,id',
                'subject_name_pending' => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date',
                'profile_photo' => 'nullable|string|max:255',
            ]);

            $user = User::findOrFail($validated['user_id']);
            if ($user->role !== 'teacher') {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'user_id' => ['Selected user must have teacher role.'],
                    ],
                ], 422);
            }

            $teacher = Teacher::create($validated);

            return response()->json([
                'message' => 'Teacher created successfully',
                'data' => $teacher,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create teacher. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get teacher details
     */
    public function show($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);

            return response()->json([
                'message' => 'Teacher details retrieved',
                'data' => $teacher,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Teacher not found',
                'error' => 'The requested teacher does not exist',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error retrieving teacher',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update teacher profile
     */
    public function update(Request $request, $id)
    {
        try {
            $teacher = Teacher::findOrFail($id);

            $validated = $request->validate([
                'user_id' => 'sometimes|required|exists:users,id|unique:teachers,user_id,' . $id,
                'teacher_code' => 'nullable|string|max:100',
                'subject_id' => 'nullable|exists:subjects,id',
                'subject_name_pending' => 'nullable|string|max:255',
                'date_of_birth' => 'nullable|date',
                'profile_photo' => 'nullable|string|max:255',
            ]);

            if (array_key_exists('user_id', $validated)) {
                $user = User::findOrFail($validated['user_id']);
                if ($user->role !== 'teacher') {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => [
                            'user_id' => ['Selected user must have teacher role.'],
                        ],
                    ], 422);
                }
            }

            $teacher->update($validated);

            return response()->json([
                'message' => 'Teacher updated successfully',
                'data' => $teacher,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Teacher not found',
                'error' => 'The teacher you are trying to update does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update teacher. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete teacher profile
     */
    public function destroy($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);
            $teacher->delete();

            return response()->json([
                'message' => 'Teacher deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Teacher not found',
                'error' => 'The teacher you are trying to delete does not exist',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete teacher',
                'error' => 'This teacher has related data. Please remove related schedules first.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete teacher',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
