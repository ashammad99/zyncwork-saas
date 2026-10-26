<?php

namespace App\Features\Landing\Services;

use App\Core\BaseService;
use App\Features\Billing\Models\Plan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The legal pages' text: markdown files the operator supplies (config
 * site.legal_path), with the operating entity's details filled in from
 * config/site.php so they are written in one place.
 */
class LegalDocumentService extends BaseService
{
    /** Marks text that still has to be replaced (config values and the shipped files). */
    public const PLACEHOLDER = 'PLACEHOLDER';

    /** Marks a point in a drafted document still to be confirmed: [[CONFIRM: ...]]. */
    public const CONFIRM = '[[CONFIRM';

    /** Document key (the route's) => file name. */
    public const DOCUMENTS = [
        'terms' => 'terms.md',
        'privacy' => 'privacy.md',
        'refunds' => 'refund-policy.md',
    ];

    public function path(string $document): string
    {
        return rtrim(config('site.legal_path'), '/\\').DIRECTORY_SEPARATOR.self::DOCUMENTS[$document];
    }

    /**
     * The document as HTML. Raw HTML in the file is escaped and unsafe links
     * (javascript: and the like) are dropped, so the file can only add text.
     */
    public function render(string $document): string
    {
        $markdown = strtr(File::get($this->path($document)), [
            ':operator_name' => config('site.operator.name'),
            ':operator_address' => config('site.operator.address'),
            ':contact_email' => config('site.operator.email'),
        ]);

        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Not finished: still the shipped placeholder, or a draft with points to confirm. */
    public function isPlaceholder(string $document): bool
    {
        $text = File::get($this->path($document));

        return str_contains($text, self::PLACEHOLDER) || str_contains($text, self::CONFIRM);
    }

    /**
     * What still has to be filled in before the public site can go live
     * (site:check).
     *
     * @return array<int, string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (['name', 'address', 'email'] as $field) {
            $value = config("site.operator.{$field}");

            if (blank($value) || str_contains($value, self::PLACEHOLDER)) {
                $problems[] = "config/site.php: operator.{$field} is not set.";
            }
        }

        foreach (array_keys(self::DOCUMENTS) as $document) {
            $path = $this->path($document);

            if (! File::exists($path)) {
                $problems[] = "{$path} is missing.";
            } elseif ($this->isPlaceholder($document)) {
                $problems[] = "{$path} is not finished (PLACEHOLDER or [[CONFIRM: ...]] left).";
            }
        }

        if (str_contains(__('site.pricing.billing_note'), self::PLACEHOLDER)) {
            $problems[] = 'lang/en/site.php: pricing.billing_note (merchant of record and tax) is not set.';
        }

        // The pricing page lists only priced plans, so an unpriced one would silently disappear.
        Plan::where('is_trial', false)->where('is_active', true)->whereNull('price_amount')
            ->orderBy('sort')
            ->pluck('key')
            ->each(function (string $key) use (&$problems) {
                $problems[] = "Plan [{$key}] is active but has no price.";
            });

        return $problems;
    }
}
