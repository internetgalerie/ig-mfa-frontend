<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Updates;

use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;

#[UpgradeWizard('igMfaFrontendCTypeMigration')]
final class IgMfaFrontendCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    protected function getListTypeToCTypeMapping(): array
    {
        return ['igmfafrontend_mfaconfiguration' => 'igmfafrontend_mfaconfiguration'];
    }

    public function getTitle(): string
    {
        return 'Migrate "Ig MfaFrontend" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "Ig MfaFrontend" plugin is now registered as content element. Update migrates existing records and backend user permissions.';
    }

}