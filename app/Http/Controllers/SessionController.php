<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class SessionController extends Controller
{
    /**
     * Get all sessions
     */
    public function index(Request $request)
    {
        try {
            $query = Session::query()->withCount('schedules');

            // Filter by name if provided
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'start_time');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination or get all
            if ($request->has('per_page')) {
                $sessions = $query->paginate($request->per_page);
            } else {
                $sessions = $query->get();
            }

            return response()->json([
                'message' => 'Session list retrieved successfully',
                'data' => $sessions,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new session
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:sessions,name',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'note' => 'nullable|string',
            ]);

            $session = Session::create($validated);

            return response()->json([
                'message' => 'Session created successfully',
                'data' => $session,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create session. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get session details
     */
    public function show($id)
    {
        try {
            $session = Session::withCount('schedules')
                ->with(['schedules' => function ($query) {
                    $query->with(['subject', 'subSubject', 'teacher.user', 'classRoom']);
                }])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Session details retrieved',
                'data' => $session,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Session not found',
                'error' => 'The requested session does not exist',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error retrieving session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update session
     */
    public function update(Request $request, $id)
    {
        try {
            $session = Session::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:sessions,name,' . $id,
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'note' => 'nullable|string',
            ]);

            $session->update($validated);

            return response()->json([
                'message' => 'Session updated successfully',
                'data' => $session->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Session not found',
                'error' => 'The session you are trying to update does not exist',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update session. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete session
     */
    public function destroy($id)
    {
        try {
            $session = Session::findOrFail($id);

            // Check if session has schedules
            if ($session->schedules()->count() > 0) {
                return response()->json([
                    'message' => 'Cannot delete session',
                    'error' => 'This session is being used in ' . $session->schedules()->count() . ' schedule(s). Please remove schedules first.',
                ], 409);
            }

            $session->delete();

            return response()->json([
                'message' => 'Session deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Session not found',
                'error' => 'The session you are trying to delete does not exist',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete session',
                'error' => 'This session may have related data. Please remove related data first.',
            ], 409);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sessions with their schedules (for schedule planning)
     */
    public function withSchedules(Request $request)
    {
        try {
            $sessions = Session::with(['schedules' => function ($query) use ($request) {
                if ($request->has('class_room_id')) {
                    $query->where('class_room_id', $request->class_room_id);
                }
                if ($request->has('teacher_id')) {
                    $query->where('teacher_id', $request->teacher_id);
                }
                if ($request->has('day')) {
                    $query->where('day', $request->day);
                }
                $query->with(['subject', 'subSubject', 'teacher.user', 'classRoom']);
            }])
            ->orderBy('start_time')
            ->get();

            return response()->json([
                'message' => 'Sessions with schedules retrieved successfully',
                'data' => $sessions,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
