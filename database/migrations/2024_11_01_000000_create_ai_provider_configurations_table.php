<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Provider identifier (openai, anthropic, groq)');
            $table->string('display_name')->comment('Human-readable name');
            $table->string('api_key')->comment('Encrypted API key');
            $table->string('default_model')->comment('Default model for this provider');
            $table->integer('default_max_tokens')->default(1024)->comment('Default max tokens');
            $table->decimal('default_temperature', 3, 1)->default(0.7)->comment('Default temperature (0.0-2.0)');
            $table->boolean('is_default')->default(false)->comment('Whether this is the default provider');
            $table->boolean('is_enabled')->default(true)->comment('Whether this provider is enabled');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamps();

            $table->index(['name', 'is_enabled']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configurations');
    }
};
