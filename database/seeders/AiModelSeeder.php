<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $models = [
            'chatgpt-4o-latest',
            'claude-3-5-sonnet-20241022',
            'claude-3-7-sonnet-20250219',
            'claude-3-7-sonnet-20250219-thinking',
            'claude-opus-4-20250514',
            'claude-sonnet-4-20250514',
            'deepseek-chat',
            'deepseek-reasoner',
            'gemini-1.5-pro',
            'gemini-1.5-pro-002',
            'gemini-1.5-pro-latest',
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-lite-001',
            'gemini-2.0-flash-lite-preview',
            'gemini-2.0-flash-lite-preview-02-05',
            'gemini-2.0-flash-live-001',
            'gemini-2.0-flash-preview-image-generation',
            'gemini-2.5-flash',
            'gemini-2.5-flash-exp-native-audio-thinking-dialog',
            'gemini-2.5-flash-lite-preview-06-17',
            'gemini-2.5-flash-preview-native-audio-dialog',
            'gemini-2.5-pro',
            'gemini-live-2.5-flash-preview',
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4.1-nano',
            'gpt-4.5-preview',
            'gpt-4o',
            'gpt-4o-audio-preview',
            'gpt-4o-mini',
            'grok-3',
            'grok-3-fast',
            'grok-3-mini',
            'grok-3-mini-fast',
            'grok-4',
            'o1',
            'o1-mini',
            'o3',
            'o3-mini',
            'o3-mini-high',
            'o3-mini-low',
            'o4-mini',
            'qwen3-235b-a22b',
        ];

        foreach ($models as $model) {
            AiModel::firstOrCreate(['name' => $model]);
        }
    }
}
