<?php

declare(strict_types=1);

namespace Mitalteli\Webtrees\Module\ShowXref;

use Fisharebest\Webtrees\Services\ModuleService;
use Mitalteli\Webtrees\Module\ShowXref\ShowXrefModule;

require __DIR__ . '/ShowXrefModule.php';

$moduleService = ShowXrefModule::getClass(ModuleService::class);
return new ShowXrefModule($moduleService);   
