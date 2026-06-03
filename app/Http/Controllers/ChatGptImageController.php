<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ChatGptImageController extends Controller
{
    // Simple test to verify API token is working
    public function index(Request $request)
    {
        try {
            $client = new Client();

            // Simple API call to test token
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $request->message
                        ]
                    ],
                    'max_tokens' => 50,
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            return response()->json([
                'success' => true,
                'message' => 'Token is valid and working!',
                'response' => $body['choices'][0]['message']['content'],
                'token_preview' => substr(env('OPENAI_API_KEY'), 0, 10) . '...',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'hint' => 'Check if your OPENAI_API_KEY is set in .env file'
            ], 500);
        }
    }

    // NEW: Simple image generation function
    public function generateImage(Request $request)
    {
        // Validate the prompt
        $request->validate([
            'prompt' => 'required|string|min:3|max:1000',
        ]);

        try {
            $client = new Client();

            // Call DALL-E API to generate image
            $response = $client->post('https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-2',  // Using DALL-E 2 for testing
                    'prompt' => $request->prompt,
                    'n' => 1,  // Number of images to generate
                    'size' => '512x512',  // Image size (256x256, 512x512, or 1024x1024)
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            // Extract the image URL from response
            $imageUrl = $body['data'][0]['url'] ?? null;

            return response()->json([
                'success' => true,
                'message' => 'Image generated successfully!',
                'prompt' => $request->prompt,
                'image_url' => $imageUrl,
                'note' => 'Copy this URL and paste in browser to view/download the image'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'hint' => 'Make sure your API key has access to DALL-E'
            ], 500);
        }
    }
}
