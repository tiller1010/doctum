<?php

declare(strict_types = 1);

namespace Doctum;

// To be able to fetch the version in this script afterwards
require_once __DIR__ . '/../src/Doctum.php';

use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveFilterIterator;
use FilesystemIterator;

$srcRoot   = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$buildRoot = realpath(__DIR__ . '/../build');

if (file_exists($buildRoot . '/doctum.phar')) {
    echo 'The phar file should not exist' . PHP_EOL;
    exit(1);
}

if (! is_dir($buildRoot)) {
    mkdir($buildRoot);
}

$version = \Doctum\Doctum::VERSION;

$pharAlias = 'doctum-' . $version . '.phar';

$phar = new Phar(
    $buildRoot . '/doctum.phar',
    FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
    $pharAlias
);

$shebang = '#!/usr/bin/env php';

$date = date('c');

// See: https://github.com/zendtech/ZendOptimizerPlus/issues/115#issuecomment-25612769
// See: https://stackoverflow.com/a/13896692/5155484
$stub = <<<STUB
<?php
/**
 * Doctum phar, generated by Doctum the $date
 * @see https://github.com/code-lts/doctum#readme
 * @version $version
 * @license MIT
 */
if (! class_exists('Phar')) {
    echo 'You seem to be missing the Phar extension' . PHP_EOL;
    echo 'Please read: https://stackoverflow.com/a/8851170/5155484 to find a solution' . PHP_EOL;
    echo 'You can also ask for help on https://github.com/code-lts/doctum/issues if you think this is bug' . PHP_EOL;
    exit(1);
}
Phar::mapPhar('$pharAlias');
include 'phar://' . __FILE__ . '/bin/doctum-binary.php';
__HALT_COMPILER();
STUB;

$iterator = new RecursiveDirectoryIterator($srcRoot);
$iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
final class PharFilterIterator extends RecursiveFilterIterator
{

    /**
     * @var string[]
     */
    private static $acceptedFiles = [];

    /**
     * @var string[]
     */
    private static $excludedFiles = [];

    /**
     * @var string[]
     */
    private static $excludedFolders = [];

    /**
     * @var string[]
     */
    private static $excludedFilesNames = [
        '.editorconfig',
        'easy-coding-standard.neon',
        '.travis.yml',
        'psalm.xml',
        '.coveralls.yml',
        'appveyor.yml',
        'phpunit.xml',
        'phive.xml',
        'Makefile',
        'phpbench.json',
        '.php_cs.dist',
        'psalm.xml',
        'phpstan.neon',
        'phpstan.neon',
        'phpcs.xml.dist',
        'phpunit.xml.dist',
        '.scrutinizer.yml',
        '.gitattributes',
        '.gitignore',
        '.env',
        'CHANGELOG',
        'README',
        'Readme.php',
        '.php_cs.cache',
        '.php_cs',
        'makefile',
        '.phpunit.result.cache',
        'phpstan.neon.dist',
        'phpstan-baseline.neon',
        'composer.lock',
        'composer.json',
        'phpmd.xml.dist',
        '.travis.php.ini',
    ];

    /**
     * @var string[]
     */
    private static $excludedFilesExtensions = [
        'rst',
        'md',
        'po',
        'po~',
        'mo~',
        'pot',
        'pot~',
        'm4',
        'c',
        'h',
        'sh',
        'w32',
    ];

    /**
     * @var string[]
     */
    private static $excludedFolderNames = [
        'tests',
        'Tests',
        'test',
        '.dependabot',
        '.github',
        '.circleci',
        'examples',
    ];

    /**
     * @var string[]
     */
    private static $excludedFolderPaths = [
        'src/Resources/themes/default/data',
        'vendor/symfony/yaml/Resources/bin',
        'vendor/bin',
        'vendor/nikic/php-parser/bin',
        'vendor/twig/twig/doc',
        'scripts',
        '.git',
        'cache',
        'build',
    ];

    public function accept()
    {
        global $srcRoot;

        /** @var \SplFileInfo $current */
        $current = $this->current();

        $relativePath = str_replace($srcRoot, '', $current->getPathname());

        if ($current->isDir()) {
            $isExcludedFolderName = in_array($current->getBasename(), static::$excludedFolderNames);
            $isExcludedFolderPath = in_array($relativePath, static::$excludedFolderPaths);

            if ($isExcludedFolderName || $isExcludedFolderPath) {
                static::$excludedFolders[] = $relativePath;
                return false;
            }
            return true;
        }

        $isExcludedFile      = in_array($current->getBasename(), static::$excludedFilesNames);
        $isExcludedExtension = in_array($current->getExtension(), static::$excludedFilesExtensions);

        if ($isExcludedFile || $isExcludedExtension) {
            static::$excludedFiles[] = $relativePath;
            return false;
        }

        static::$acceptedFiles[] = $relativePath;

        return true;
    }

    /**
     * @return string[]
     */
    public static function getAcceptedFiles(): array
    {
        return static::$acceptedFiles;
    }

    /**
     * @return string[]
     */
    public static function getExcludedFiles(): array
    {
        return static::$excludedFiles;
    }

    /**
     * @return string[]
     */
    public static function getExcludedFolders(): array
    {
        return static::$excludedFolders;
    }

}

$filter = new PharFilterIterator($iterator);

$pharFilesList = new RecursiveIteratorIterator($filter);

$phar->setStub($shebang . PHP_EOL . $stub);
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->buildFromIterator($pharFilesList, $srcRoot);
$phar->setMetadata(
    [
    'vcs.git' => 'https://github.com/code-lts/doctum.git',
    'vcs.browser' => 'https://github.com/code-lts/doctum',
    'version' => $version,
    'build-date' => $date,
    'license' => 'MIT',
    'vendor' => 'Doctum',
    'name' => 'Doctum',
    ]
);

$files = array_map(
    static function (string $fileRelativePath) {
        return [
            'name' => $fileRelativePath,
            'sha256' => hash_file('sha256', $fileRelativePath),
        ];
    },
    PharFilterIterator::getAcceptedFiles()
);

$manifest = [
    'files' => $files,
    'excludedFiles' => PharFilterIterator::getExcludedFiles(),
    'excludedFolders' => PharFilterIterator::getExcludedFolders(),
    'phar' => [
        'sha256' => $phar->getSignature()['hash'],
        'numberOfFiles' => $phar->count(),
    ]
];

file_put_contents($buildRoot . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
