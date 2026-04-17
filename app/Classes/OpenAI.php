<?php

namespace App\Classes;

use Illuminate\Support\Facades\Http;

final class OpenAI
{
    public static function get_creds()
    {
        return [
            'apiKey' => config('services.open_ai.api_key'),
            'apiBase' => config('services.open_ai.api_base'),
            'chatModel' => config('services.open_ai.model'),
        ];
    }

    public static function chat(array $data, $model = null)
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/chat/completions';
        $data['model'] = $model ?? $creds['chatModel'];
        $data['service_tier'] = 'priority';

        return Http::timeout(120)->withHeaders(['Authorization' => 'Bearer '.$creds['apiKey'], 'Content-Type' => 'application/json'])->post($url, $data);
    }

    public static function whisper(string $file_name)
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/audio/transcriptions';

        // $data['model'] = 'whisper-1';
        return Http::timeout(120)->withHeaders(['Authorization' => 'Bearer '.$creds['apiKey']])
            ->attach('file', fopen($file_name, 'r'))->post($url, ['model' => 'whisper-1']);
    }

    public static function createEmbedding(string $text)
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/embeddings';
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$creds['apiKey']])
            ->post($url, ['input' => $text, 'model' => 'text-embedding-3-large']);

        return $response;
    }

    public static function speech(string $text)
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/audio/speech';

        return Http::timeout(120)->withHeaders(['Authorization' => 'Bearer '.$creds['apiKey'], 'Content-Type' => 'application/json'])->post($url, ['model' => 'tts-1', 'voice' => 'alloy', 'input' => $text]);
    }

    /**
     * Perform a web search using OpenAI's web search tool
     *
     * @param  string  $input  The user's query or prompt
     * @param  string  $model  The model to use (default: gpt-4.1)
     * @param  array  $options  Additional options for the web search
     * @return \Illuminate\Http\Client\Response
     */
    public static function webSearch(string $input, string $model = 'gpt-4.1', array $options = [])
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/responses';

        $data = [
            'model' => $model,
            'tools' => [['type' => 'web_search_preview']],
            'input' => $input,
        ];

        // Add optional web search configurations if provided
        if (isset($options['user_location'])) {
            $data['tools'][0]['user_location'] = $options['user_location'];
        }

        if (isset($options['search_context_size'])) {
            $data['tools'][0]['search_context_size'] = $options['search_context_size'];
        }

        // Add tool_choice if specified
        if (isset($options['tool_choice'])) {
            $data['tool_choice'] = $options['tool_choice'];
        }

        return Http::timeout(180)->withHeaders([
            'Authorization' => 'Bearer '.$creds['apiKey'],
            'Content-Type' => 'application/json',
        ])->post($url, $data);
    }

    /**
     * Call OpenAI with structured JSON output using json_schema
     *
     * @param  array  $messages  Array of message objects with role and content
     * @param  array  $schema  JSON schema for structured output
     * @param  string  $schemaName  Name for the schema
     * @param  string  $model  Model to use (default: gpt-4o-mini)
     * @param  array  $options  Additional chat/completions parameters (e.g. temperature)
     * @return \Illuminate\Http\Client\Response
     */
    public static function chatWithStructuredOutput(array $messages, array $schema, string $schemaName = 'response', ?string $model = null, array $options = [])
    {
        $creds = self::get_creds();
        $url = $creds['apiBase'].'/chat/completions';

        $model = $model ?? $creds['chatModel'];

        $data = [
            'model' => $model,
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        if ($options !== []) {
            $data = array_merge($data, $options);
        }

        return Http::timeout(300)->withHeaders([
            'Authorization' => 'Bearer '.$creds['apiKey'],
            'Content-Type' => 'application/json',
        ])->post($url, $data);
    }
}
