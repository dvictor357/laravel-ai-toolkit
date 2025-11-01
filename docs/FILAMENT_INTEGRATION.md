# Filament Admin Panel Integration Guide

## Overview

The Laravel AI Toolkit now includes optional Filament Admin Panel integration, providing a beautiful and functional web interface for managing AI providers, monitoring usage, and testing AI operations.

## Features

### 🎯 Core Features
- **AI Provider Management**: Configure and manage multiple AI providers (OpenAI, Anthropic, Groq)
- **Real-time Chat Interface**: Interactive chat dashboard with streaming support
- **Usage Analytics**: Monitor AI usage with real-time statistics and metrics
- **Provider Testing**: Built-in connection testing for all AI providers
- **Configuration Management**: Easy-to-use forms for AI settings

### 📊 Dashboard Components
- **Usage Statistics Widget**: Real-time metrics for requests, cache hit rates, response times
- **AI Chat Dashboard**: Interactive interface for testing AI providers
- **Provider Configuration**: Visual management of API keys and settings
- **Conversation History**: Track and export chat conversations

## Installation

### 1. Install Filament (Already Done)

Filament v4.x has been automatically installed via Composer:

```bash
composer require filament/filament:"^4.0"
```

### 2. Configure Environment

Add the following environment variables to your `.env` file:

```env
# Enable Filament Admin Panel
AI_TOOLKIT_FILAMENT_ENABLED=true
AI_TOOLKIT_FILAMENT_PATH=admin

# Optional: Disable specific features
AI_TOOLKIT_FILAMENT_REALTIME_CHAT=true
AI_TOOLKIT_FILAMENT_USAGE_ANALYTICS=true
AI_TOOLKIT_FILAMENT_PROVIDER_TESTING=true
```

### 3. Run Database Migration

Add the migration to your Laravel application:

```bash
php artisan vendor:publish --provider="AIToolkit\AIToolkit\AIToolkitServiceProvider" --tag="ai-toolkit-migrations"
php artisan migrate
```

Or copy the migration manually:

```bash
cp vendor/aitoolkit/laravel-ai-toolkit/database/migrations/2024_11_01_000000_create_ai_provider_configurations_table.php database/migrations/
php artisan migrate
```

### 4. Seed Initial AI Providers (Optional)

Seed default AI providers from your `.env` file:

```bash
php artisan db:seed --class="AIToolkit\AIToolkit\Database\Seeders\AIProviderSeeder"
```

Or add to your `DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call(AIToolkit\AIToolkit\Database\Seeders\AIProviderSeeder::class);
}
```

**Note:** The seeder will only run if no providers exist yet. You can always add providers manually via the admin panel at `/admin`.

### 5. Configure Authentication

Ensure your Laravel application has authentication configured:

```bash
# If using Laravel Breeze
php artisan breeze:install

# Or install other auth scaffolding as needed
```

### 5. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="AIToolkit\AIToolkit\AIToolkitServiceProvider" --tag="ai-toolkit-config"
```

## Usage

### Accessing the Admin Panel

1. Navigate to `/admin` in your Laravel application
2. Log in with your application credentials
3. You'll see the AI Toolkit Admin Panel with:
   - AI Providers management
   - Usage statistics
   - AI Chat dashboard
   - Configuration options

### Managing AI Providers

1. Go to **AI Management > AI Providers**
2. Click **Add Provider** to configure new providers
3. Set API keys, default models, and options
4. Test connections using the **Test Connection** action
5. Set one provider as the default

### Using the Chat Dashboard

1. Navigate to **AI Management > AI Chat**
2. Select your preferred provider and model
3. Enter your message and send
4. View real-time responses and conversation history
5. Export conversations for record keeping

### Monitoring Usage

- View real-time statistics on the dashboard
- Monitor cache hit rates and response times
- Track success/error rates
- Analyze provider performance

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `AI_TOOLKIT_FILAMENT_ENABLED` | Enable/disable Filament integration | `true` |
| `AI_TOOLKIT_FILAMENT_PATH` | Admin panel URL path | `admin` |
| `AI_TOOLKIT_FILAMENT_REALTIME_CHAT` | Enable real-time chat features | `true` |
| `AI_TOOLKIT_FILAMENT_USAGE_ANALYTICS` | Enable usage analytics | `true` |
| `AI_TOOLKIT_FILAMENT_PROVIDER_TESTING` | Enable provider testing | `true` |

### Configuration File

Access Filament settings via `config/ai-toolkit.php`:

```php
'filament' => [
    'enabled' => true,
    'panel_path' => 'admin',
    'middleware' => [
        'auth' => true,
        'verified' => false,
    ],
    'features' => [
        'real_time_chat' => true,
        'usage_analytics' => true,
        'provider_testing' => true,
    ],
],
```

## Architecture

### File Structure

```
src/Filament/
├── AdminPanelProvider.php          # Main panel configuration
├── FilamentServiceProvider.php      # Package service provider
├── Resources/
│   └── AIProviderResource.php       # AI provider management
├── Pages/
│   └── AIChatDashboard.php         # Chat interface
└── Widgets/
    └── AIUsageStatsWidget.php       # Statistics widget
```

### Service Registration

The Filament integration is automatically registered in `AIToolkitServiceProvider.php`:

```php
if (config('ai-toolkit.filament.enabled', true) && class_exists(\Filament\PanelProvider::class)) {
    $this->app->register(\AIToolkit\AIToolkit\Filament\FilamentServiceProvider::class);
}
```

## Security Considerations

### API Key Storage
- API keys are encrypted in the database
- Keys are masked in the admin interface
- Only users with proper permissions can view/edit keys

### Access Control
- Admin panel requires authentication
- Middleware can be customized in configuration
- Route protection follows Laravel's auth patterns

### Input Validation
- All form inputs are validated
- XSS protection through proper escaping
- CSRF protection via Laravel's built-in mechanisms

## Troubleshooting

### Common Issues

**1. Admin Panel Not Loading**
- Ensure Filament is installed: `composer require filament/filament:"^4.0"`
- Check that authentication is configured
- Verify environment variables are set

**2. Migration Errors**
- Ensure migration file exists in your application
- Check database connection and permissions
- Run `php artisan migrate:status` to check migration status

**3. Provider Test Failures**
- Verify API keys are correct
- Check internet connectivity
- Ensure provider accounts have sufficient credits

**4. Missing Widgets/Features**
- Clear config cache: `php artisan config:clear`
- Restart the application server
- Check environment variable settings

### Debug Commands

```bash
# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check Filament installation
php artisan filament:version

# Test provider connections via CLI
php artisan ai:test openai
php artisan ai:test anthropic
php artisan ai:test groq
```

## Advanced Configuration

### Custom Middleware

```php
'filament' => [
    'middleware' => [
        'auth' => true,
        'verified' => true,
        'custom' => ['role:admin'],
    ],
],
```

### Custom Styling

Override Filament views in your application:

```bash
php artisan filament:publish-styles
php artisan filament:publish-scripts
```

### Database Customization

The AI provider configuration table can be customized by modifying the migration file before publishing.

## Contributing

To extend the Filament integration:

1. Create new resources in `src/Filament/Resources/`
2. Add pages in `src/Filament/Pages/`
3. Create widgets in `src/Filament/Widgets/`
4. Update `AdminPanelProvider.php` to register new components

## Support

For issues related to the Filament integration:

1. Check the troubleshooting section
2. Review Laravel and Filament documentation
3. Check GitHub issues for the package
4. Contact support with detailed error information

---

**Integration Status**: ✅ Implemented and Ready for Use

This Filament Admin Panel integration provides a complete management interface for the Laravel AI Toolkit, making it easy to configure, monitor, and use AI providers through a beautiful web interface.
