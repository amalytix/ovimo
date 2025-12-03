<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->text('openai_api_key')->nullable()->after('relevancy_prompt');
            $table->string('openai_model', 50)->default('gpt-5-mini')->after('openai_api_key');
            $table->text('gemini_api_key')->nullable()->after('openai_model');
            $table->string('gemini_image_model', 100)->default('gemini-3-pro-image-preview')->after('gemini_api_key');
            $table->string('gemini_image_size', 10)->default('1K')->after('gemini_image_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'openai_api_key',
                'openai_model',
                'gemini_api_key',
                'gemini_image_model',
                'gemini_image_size',
            ]);
        });
    }
};
