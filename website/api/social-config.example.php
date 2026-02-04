<?php
/**
 * Social Media and AI API Configuration Example
 *
 * This is a TEMPLATE file showing all API credentials needed for the system.
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to: social-config.php
 * 2. Fill in all your API keys and credentials
 * 3. NEVER commit social-config.php to git - it contains sensitive credentials
 * 4. Add social-config.php to .gitignore (already done)
 * 5. Deploy social-config.php to your server via scp, NOT git
 *
 * SECURITY NOTES:
 * - Keep this file out of version control
 * - Keep the actual social-config.php file secure on your server
 * - Regenerate API keys if they are ever exposed
 * - Use strong, unique credentials
 * - Rotate keys regularly
 */

return [
    /**
     * Twitter/X API Credentials
     *
     * Setup: https://developer.x.com/
     * Pricing: Pay-per-use (~$0.01-0.015 per tweet, $5 minimum credit)
     * Status: Working with OAuth 1.0a
     */
    'twitter' => [
        'api_key' => 'YOUR_TWITTER_API_KEY',
        'api_secret' => 'YOUR_TWITTER_API_SECRET',
        'access_token' => 'YOUR_ACCESS_TOKEN',
        'access_token_secret' => 'YOUR_ACCESS_TOKEN_SECRET',
        'bearer_token' => 'YOUR_BEARER_TOKEN'
    ],

    /**
     * Meta/Instagram API Credentials
     *
     * Setup: https://developers.facebook.com/
     * App Name: PROMPT
     * Status: Partial setup - needs instagram_content_publish permission
     *
     * To complete Instagram setup:
     * 1. Meta Developer Portal → PROMPT app → Permissions and Features
     * 2. Add instagram_content_publish permission
     * 3. Go to Use Cases → Instagram → Generate access tokens
     * 4. Use helper: https://promptband.ai/api/meta-token-helper.php
     */
    'meta' => [
        'app_id' => 'YOUR_META_APP_ID',
        'app_secret' => 'YOUR_META_APP_SECRET',
        'page_id' => 'YOUR_FACEBOOK_PAGE_ID',
        'page_access_token' => 'YOUR_FACEBOOK_PAGE_ACCESS_TOKEN',
        'instagram_account_id' => 'YOUR_INSTAGRAM_BUSINESS_ACCOUNT_ID',
        'instagram_access_token' => 'YOUR_INSTAGRAM_ACCESS_TOKEN'
    ],

    /**
     * Claude/Anthropic API Credentials
     *
     * Setup: https://console.anthropic.com/
     * Usage: AI-powered content generation, moderation, etc.
     * Documentation: https://docs.anthropic.com/
     */
    'anthropic' => [
        'api_key' => 'YOUR_ANTHROPIC_API_KEY'
    ],

    /**
     * OpenAI API Credentials
     *
     * Setup: https://platform.openai.com/
     * Usage: DALL-E image generation, GPT models for content creation
     * Note: Requires paid account with API access enabled
     */
    'openai' => [
        'api_key' => 'YOUR_OPENAI_API_KEY'
    ],

    /**
     * Replicate API Token
     *
     * Setup: https://replicate.com/
     * Usage: Video animation, image-to-video generation
     * Model: Minimax video-01 for music video clips
     * Pricing: Pay-per-use based on generation time
     *
     * Also set in .mcp.json for Claude Code MCP server:
     * {
     *   "mcpServers": {
     *     "replicate": {
     *       "type": "stdio",
     *       "command": "npx",
     *       "args": ["-y", "mcp-replicate"],
     *       "env": {
     *         "REPLICATE_API_TOKEN": "YOUR_REPLICATE_API_TOKEN"
     *       }
     *     }
     *   }
     * }
     */
    'replicate' => [
        'api_token' => 'YOUR_REPLICATE_API_TOKEN'
    ],

    /**
     * Suno API Configuration
     *
     * Self-hosted Suno API using gcui-art/suno-api
     * GitHub: https://github.com/gcui-art/suno-api
     *
     * Setup:
     * 1. Clone: git clone https://github.com/gcui-art/suno-api.git
     * 2. Configure with your Suno cookies (get from browser dev tools while logged into Suno.com)
     * 3. Run: docker-compose up -d
     * 4. Update api_url below to point to your instance
     *
     * Default: http://localhost:3000 (local Docker)
     * Remote: https://your-server.com:3000 (deployed instance)
     */
    'suno' => [
        'api_url' => 'http://localhost:3000'
    ],

    /**
     * Claude/Anthropic API Credentials (legacy key location)
     * Some older code may reference this location
     */
    'claude' => [
        'api_key' => 'YOUR_ANTHROPIC_API_KEY'
    ]
];
?>
