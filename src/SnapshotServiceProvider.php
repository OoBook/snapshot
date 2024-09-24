<?php

namespace Oobook\Snapshot;

use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

class SnapshotServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('snapshot')
            ->hasConfigFile(['snapshot'])
            // ->hasViews()
            // ->hasViewComponent('spatie', Alert::class)
            // ->hasViewComposer('*', MyViewComposer::class)
            // ->sharesDataWithAllViews('downloads', 3)
            // ->hasTranslations()
            // ->hasAssets()
            // ->publishesServiceProvider('MyProviderName')
            // ->hasRoute('web')
            // ->hasCommand(YourCoolPackageCommand::class)
            // ->hasInstallCommand(function(InstallCommand $command) {
            //     $command
            //         ->publishConfigFile()
            //         ->publishAssets()
            //         ->publishMigrations()
            //         ->copyAndRegisterServiceProviderInApp()
            //         ->askToStarRepoOnGitHub();
            // });
            ->hasMigrations(['create_snapshots_table']);
    }
}
