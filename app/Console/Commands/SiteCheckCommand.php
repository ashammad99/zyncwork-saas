<?php

namespace App\Console\Commands;

use App\Features\Landing\Services\LegalDocumentService;
use Illuminate\Console\Command;

/**
 * Lists what the public site still needs before it can go live: the
 * operator's details, the legal documents, the pricing page's billing line,
 * and a price for every active paid plan. Fails while anything is missing;
 * run it before each deploy.
 */
class SiteCheckCommand extends Command
{
    protected $signature = 'site:check';

    protected $description = 'Check that the public site has no placeholders left';

    public function handle(LegalDocumentService $legalDocuments): int
    {
        $problems = $legalDocuments->problems();

        if ($problems === []) {
            $this->info('The public site is ready: no placeholders left.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
