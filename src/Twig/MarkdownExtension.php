<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [$this, 'formatMarkdown'], ['is_safe' => ['html']]),
        ];
    }

    public function formatMarkdown(string $text): string
    {
        // Escape HTML entities for security
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Convert newlines to <br> tags (matches nl2br filter)
        $formatted = nl2br($escaped);
        
        // Headers - process before other formatting
        $formatted = preg_replace('/^### (.*?)(<br \/>|$)/m', '<h3 style="font-size: 1.25rem; font-weight: bold; margin: 0.5rem 0;">$1</h3>$2', $formatted);
        $formatted = preg_replace('/^## (.*?)(<br \/>|$)/m', '<h2 style="font-size: 1.5rem; font-weight: bold; margin: 0.75rem 0;">$1</h2>$2', $formatted);
        $formatted = preg_replace('/^# (.*?)(<br \/>|$)/m', '<h1 style="font-size: 1.75rem; font-weight: bold; margin: 1rem 0;">$1</h1>$2', $formatted);
        
        // Bold: **text** -> <strong>text</strong>
        $formatted = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $formatted);
        
        // Italic: *text* -> <em>text</em> (but not inside ** bold text)
        $formatted = preg_replace('/(?<!\*)\*([^*<>]+?)\*(?!\*)/s', '<em>$1</em>', $formatted);
        
        // Code: `code` -> <code>code</code>
        $formatted = preg_replace('/`([^`]+)`/', '<code style="background-color: #f3f4f6; padding: 0.125rem 0.25rem; border-radius: 0.25rem; font-family: monospace;">$1</code>', $formatted);
        
        // Unordered lists: - item or * item
        $formatted = preg_replace('/^[-*] (.*?)(<br \/>|$)/m', '<li style="margin-left: 1rem;">$1</li>$2', $formatted);
        
        // Ordered lists: 1. item, 2. item etc.
        $formatted = preg_replace('/^\d+\. (.*?)(<br \/>|$)/m', '<li style="margin-left: 1rem; list-style-type: decimal;">$1</li>$2', $formatted);
        
        // Links: [text](url) -> <a href="url">text</a>
        $formatted = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer" style="color: #3b82f6; text-decoration: underline;">$1</a>', $formatted);
        
        // Wrap consecutive list items in ul/ol tags
        $formatted = preg_replace_callback('/(<li[^>]*>.*?<\/li>(<br \/>)?)+/', function($matches) {
            $match = $matches[0];
            if (strpos($match, 'list-style-type: decimal') !== false) {
                return '<ol style="margin: 0.5rem 0;">' . str_replace('<br />', '', $match) . '</ol>';
            } else {
                return '<ul style="margin: 0.5rem 0; list-style-type: disc;">' . str_replace('<br />', '', $match) . '</ul>';
            }
        }, $formatted);
        
        return $formatted;
    }
}