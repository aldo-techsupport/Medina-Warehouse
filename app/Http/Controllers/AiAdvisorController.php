<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Models\AiChatMessage;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiAdvisorController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Display AI Sales Advisor dashboard & Chat interface.
     */
    public function index(Request $request): View
    {
        // Get or generate latest analysis
        $latestAnalysis = AiAnalysis::latest()->first();

        if (! $latestAnalysis) {
            $latestAnalysis = $this->aiService->generateSalesAnalysis(auth()->id());
        }

        $metrics = $this->aiService->gatherWarehouseMetrics();

        // Get current chat session
        $sessionId = $request->session()->get('ai_chat_session_id');
        if (! $sessionId) {
            $sessionId = (string) Str::uuid();
            $request->session()->put('ai_chat_session_id', $sessionId);
        }

        $chatMessages = AiChatMessage::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->oldest()
            ->get();

        $aiConfig = [
            'is_configured' => $this->aiService->isConfigured(),
            'base_url' => config('ai.base_url'),
            'model' => config('ai.model'),
            'timeout' => config('ai.timeout'),
        ];

        return view('ai.index', compact(
            'latestAnalysis',
            'metrics',
            'chatMessages',
            'sessionId',
            'aiConfig'
        ));
    }

    /**
     * Trigger a fresh sales and marketing analysis.
     */
    public function analyze(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $analysis = $this->aiService->generateSalesAnalysis(auth()->id());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Analisis data penjualan dan strategi pemasaran berhasil diperbarui!',
                    'analysis' => $analysis,
                ]);
            }

            return redirect()->route('ai.index')->with('success', 'Analisis penjualan & saran AI berhasil diperbarui!');
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui analisis: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->route('ai.index')->with('error', 'Gagal memperbarui analisis: '.$e->getMessage());
        }
    }

    /**
     * Process an interactive seller chat message.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:100',
        ]);

        $sessionId = $validated['session_id'] ?? $request->session()->get('ai_chat_session_id') ?? (string) Str::uuid();
        $request->session()->put('ai_chat_session_id', $sessionId);

        $response = $this->aiService->chat(
            user: $request->user(),
            userMessage: $validated['message'],
            sessionId: $sessionId
        );

        return response()->json($response);
    }

    /**
     * Clear the current user's chat history for the active session.
     */
    public function clearChat(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id') ?? $request->session()->get('ai_chat_session_id');

        if ($sessionId) {
            AiChatMessage::where('user_id', auth()->id())
                ->where('session_id', $sessionId)
                ->delete();
        } else {
            AiChatMessage::where('user_id', auth()->id())->delete();
        }

        // Generate a new fresh session ID
        $newSessionId = (string) Str::uuid();
        $request->session()->put('ai_chat_session_id', $newSessionId);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat obrolan AI telah dibersihkan.',
            'new_session_id' => $newSessionId,
        ]);
    }

    /**
     * Test the AI router connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $result = $this->aiService->testConnection();

        return response()->json($result);
    }
}
