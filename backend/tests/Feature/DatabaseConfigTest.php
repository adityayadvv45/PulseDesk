<?php

describe('database configuration', function () {
    it('resolves sqlite database paths to an absolute path', function () {
        $original = env('DB_DATABASE');
        putenv('DB_DATABASE=database/database.sqlite');

        $config = require base_path('config/database.php');
        $resolved = $config['connections']['sqlite']['database'];

        expect($resolved)->toBeString();
        expect(realpath($resolved))->toBe(realpath(base_path('database/database.sqlite')));

        if ($original === false) {
            putenv('DB_DATABASE');
        } else {
            putenv("DB_DATABASE={$original}");
        }
    });
});
