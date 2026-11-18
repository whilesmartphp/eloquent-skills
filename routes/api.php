<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Skills\Support\SkillsConfig;

$skills = SkillsConfig::controller('skills');
$versions = SkillsConfig::controller('versions');
$imports = SkillsConfig::controller('imports');
$sources = SkillsConfig::controller('sources');
$write = (array) config('skills.write_middleware', []);
$enabled = fn (string $group, string $action): bool => config("skills.route_groups.{$group}", true)
    && ! in_array($action, (array) config('skills.disabled_routes', []), true);

if ($enabled('skills', 'index')) {
    Route::get('skills', [$skills, 'index'])->name('index');
}
if ($enabled('skills', 'show')) {
    Route::get('skills/{skill}', [$skills, 'show'])->whereUuid('skill')->name('show');
}
if ($enabled('skills', 'validation')) {
    Route::get('skills/{skill}/validation', [$skills, 'validation'])->whereUuid('skill')->name('validation');
}
if ($enabled('versions', 'versions.index')) {
    Route::get('skills/{skill}/versions', [$versions, 'index'])->whereUuid('skill')->name('versions.index');
}
if ($enabled('versions', 'versions.show')) {
    Route::get('skills/{skill}/versions/{version}', [$versions, 'show'])->whereUuid(['skill', 'version'])->name('versions.show');
}
if ($enabled('sources', 'sources.index')) {
    Route::get('skill-sources', [$sources, 'index'])->name('sources.index');
}
if ($enabled('imports', 'imports.index')) {
    Route::get('skill-imports', [$imports, 'index'])->name('imports.index');
}
if ($enabled('imports', 'imports.show')) {
    Route::get('skill-imports/{import}', [$imports, 'show'])->whereUuid('import')->name('imports.show');
}

Route::middleware($write)->group(function () use ($skills, $versions, $imports, $enabled) {
    if ($enabled('skills', 'store')) {
        Route::post('skills', [$skills, 'store'])->name('store');
    }
    if ($enabled('skills', 'check')) {
        Route::post('skills/validate', [$skills, 'check'])->name('check');
    }
    if ($enabled('skills', 'update')) {
        Route::match(['put', 'patch'], 'skills/{skill}', [$skills, 'update'])->whereUuid('skill')->name('update');
    }
    if ($enabled('skills', 'destroy')) {
        Route::delete('skills/{skill}', [$skills, 'destroy'])->whereUuid('skill')->name('destroy');
    }
    if ($enabled('skills', 'publish')) {
        Route::post('skills/{skill}/publish', [$skills, 'publish'])->whereUuid('skill')->name('publish');
    }
    if ($enabled('versions', 'versions.revoke')) {
        Route::post('skills/{skill}/versions/{version}/revoke', [$versions, 'revoke'])->whereUuid(['skill', 'version'])->name('versions.revoke');
    }
    if ($enabled('imports', 'imports.store')) {
        Route::post('skill-imports', [$imports, 'store'])->name('imports.store');
    }
});
