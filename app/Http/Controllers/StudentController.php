<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class StudentController extends Controller
{
    /**
     * Get all students with user and classrooms relation
     */
    public function index(Request $request)
    {
        try {
            $query = Student::with(['user', 'classRooms']);
            $perPage = max(1, min((int) $request->query('per_page', 10), 500));

            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }

            $students = $query->latest()->paginate(10);
            $students = $query->latest()->paginate($perPage);

            return response()->json([
                'message' => 'Student list retrieved successfully',
                'data' => $students,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve students',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new student profile
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id|unique:students,user_id',
                'year' => 'nullable|integer|digits:4|min:1900|max:' . date('Y'),
                'date_of_birth' => 'nullable|date',
            ]);

            $user = User::findOrFail($validated['user_id']);
            if ($user->role !== 'student') {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'user_id' => ['Selected user must have student role.'],
                    ],
                ], 422);
            }

            $student = Student::create($validated);

            return response()->json([
                'message' => 'Student created successfully',
                'data' => $student->load(['user', 'classRooms']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create student. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student details
     */
    public function show($id)
    {
        try {
            $student = Student::with(['user', 'classRooms'])->findOrFail($id);

            return response()->json([
                'message' => 'Student details retrieved',
                'data' => $student,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Student not found',
                'error' => 'The requested student does not exist',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error retrieving student',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update student profile
     */
    public function update(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);

            $validated = $request->validate([
                'user_id' => 'sometimes|required|exists:users,id|unique:students,user_id,' . $id,
                'year' => 'nullable|integer|digits:4|min:1900|max:' . date('Y'),
                'date_of_birth' => 'nullable|date',
            ]);

            if (array_key_exists('user_id', $validated)) {
                $user = User::findOrFail($validated['user_id']);
                if ($user->role !== 'student') {
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => [
                            'user_id' => ['Selected user must have student role.'],
                        ],
                    ], 422);
                }
            }

            $student->update($validated);

            return response()->json([
                'message' => 'Student updated successfully',
                'data' => $student->load(['user', 'classRooms']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Student not found',
                'error' => 'The student you are trying to update does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update student. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete student profile
     */
    public function destroy($id)
    {
        try {
            $student = Student::findOrFail($id);
            $student->delete();

            return response()->json([
                'message' => 'Student deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Student not found',
                'error' => 'The student you are trying to delete does not exist',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete student',
                'error' => 'This student has related data. Please remove related classroom/schedule links first.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete student',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
