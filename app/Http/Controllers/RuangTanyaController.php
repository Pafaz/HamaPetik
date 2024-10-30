<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\RequestException;

class RuangTanyaController extends Controller
{
    public function index()
    {
        // Find or create a conversation for the authenticated user
        $conversation = Conversation::firstOrCreate(['user_id' => Auth::id()]);

        // Get all messages for this conversation
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return view('ruangbertanya.index',compact('messages'));
    }
    public function callChatbotAPI($message)
    {
        $client = new Client();
        try {
            // Kirim permintaan POST ke API
            $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . env('GEMINI_API_KEY'), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "contents" => [
                        [
                            "parts" => [
                                [
                                    "text" => $message
                                ],
                            ]
                        ]
                    ],
                ]
            ]);

            // Dapatkan respons dari API
            $responseBody = json_decode($response->getBody(), true);
            $textContent = $responseBody['candidates'][0]['content']['parts'][0]['text'];
            // return response()->json($textContent);
            return $textContent;
        } catch (RequestException $e) {
            // Tangani pengecualian permintaan
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorResponse, true);
                return response()->json(['error' => $errorData], 500);
            } else {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
    }

    public function sendMessage(Request $request)
    {
        // Find or create a conversation
        $conversation = Conversation::firstOrCreate(['user_id' => Auth::id()]);

        // Save user message
        $userMessage = new Message([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'content' => $request->input('content')
        ]);
        $userMessage->save();

        // Call chatbot API and save response
        $responseContent = $this->callChatbotAPI($request->input('content'));

        $botMessage = new Message([
            'conversation_id' => $conversation->id,
            'sender' => 'bot',
            'content' => $responseContent
        ]);
        $botMessage->save();

        // return response()->json(['message' => $responseContent]);
        return response()->json($responseContent);
    }
}
