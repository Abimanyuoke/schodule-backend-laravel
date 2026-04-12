<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class ClassRoomController extends Controller
{
    /**
     * Get all classrooms with their students
     */
    public function index()
    {
        try {
            $classes = ClassRoom::with('students')->get();

            return response()->json([
                'data' => $classes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve classrooms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new classroom
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'subject_id' => 'nullable|exists:subjects,id',
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $class = ClassRoom::create($validated);

            return response()->json([
                'message' => 'Classroom created successfully',
                'data' => $class,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create classroom. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get classroom details with students
     */
    public function show($id)
    {
        try {
            $class = ClassRoom::with('students')->findOrFail($id);

            return response()->json($class);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Classroom not found',
                'error' => 'The requested classroom does not exist',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error retrieving classroom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update classroom information
     */
    public function update(Request $request, $id)
    {
        try {
            $class = ClassRoom::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'subject_id' => 'nullable|exists:subjects,id',
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $class->update($validated);

            return response()->json([
                'message' => 'Classroom updated successfully',
                'data' => $class,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Classroom not found',
                'error' => 'The classroom you are trying to update does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update classroom. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a classroom
     */
    public function destroy($id)
    {
        try {
            $class = ClassRoom::findOrFail($id);
            $class->delete();

            return response()->json([
                'message' => 'Classroom deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Classroom not found',
                'error' => 'The classroom you are trying to delete does not exist',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete classroom',
                'error' => 'This classroom has student data. Please move or remove students first.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete classroom',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add students to a classroom
     */
    public function addStudents(Request $request, $id)
    {
        try {
            $class = ClassRoom::findOrFail($id);

            $request->validate([
                'student_ids' => 'required|array',
            ]);

            $class->students()->attach($request->student_ids);

            return response()->json([
                'message' => 'Students added to classroom successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Classroom not found',
                'error' => 'The classroom does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Some students may already be in this classroom. Please try again.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to add students',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
