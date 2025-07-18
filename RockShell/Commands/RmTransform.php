<?php

namespace RockMigrations;

use RockShell\Command;

class RmTransform extends Command
{
  public function config()
  {
    $this->setDescription("Transform the projects folder structure to RockMigrations deployments");
  }

  public function handle()
  {
    // Use rootPath for full project copy, docroot for shared assets
    $root = rtrim($this->app->rootPath(), "/");
    $docroot = rtrim($this->app->docroot(), "/");

    if (!$this->confirm("This will create a new folder structure in $root - continue?")) {
      return self::SUCCESS;
    }

    // backup current folder
    $name = 'backup-' . date('Y-m-d-His');
    $backupPath = dirname($root) . "/$name";
    if ($this->confirm("Backup current folder to $backupPath?", true)) {
      exec("cp -r $root $backupPath");
    }

    // cleanup
    exec("cd $root && rm -rf release-1");
    exec("cd $root && rm -rf current");
    exec("cd $root && rm -rf shared");

    // create folders
    $release = "$root/release-1";
    $shared = "$root/shared";
    exec("mkdir -p $release");
    exec("mkdir -p $shared");
    exec("cd $root && ln -snf release-1 current");

    // copy files from root to release-1
    foreach (glob("$root/{.,}*", GLOB_BRACE) as $file) {
      $f = basename($file);
      // skip
      if (
        $f === "."
        || $f === ".."
        || $f === "release-1"
        || $f === "current"
        || $f === "shared"
      ) continue;

      if ($f === ".github" || $f === ".vscode") {
        exec("rm -rf $file");
        continue;
      }

      exec("cd $root && cp -r $f $release");
      if (is_file($file)) exec("rm $file");
      elseif (is_dir($file)) exec("rm -rf $file");
    }

    // If docroot is not root, copy public/ into release-1/public
    if ($docroot !== $root && is_dir($docroot)) {
      exec("cp -r $docroot $release/public");
    }

    // Determine correct site path for shared assets
    $sitePath = ($docroot !== $root) ? "$release/public/site" : "$release/site";

    // copy shared assets to shared folder
    exec("mkdir -p $shared/site/assets");
    exec("cp -r $sitePath/assets/files $shared/site/assets");
    exec("cp -r $sitePath/assets/backups $shared/site/assets");
    exec("cp $sitePath/config-local.php $shared/site/config-local.php");

    // add symlinks for these files
    exec("cd $root && rm -rf $sitePath/assets/files && ln -snf ../../../shared/site/assets/files $sitePath/assets/files");
    exec("cd $root && rm -rf $sitePath/assets/backups && ln -snf ../../../shared/site/assets/backups $sitePath/assets/backups");
    exec("cd $root && rm -rf $sitePath/config-local.php && ln -snf ../../shared/site/config-local.php $sitePath/config-local.php");

    // remove backup folder?
    if ($this->confirm("Remove backup folder $backupPath?")) {
      exec("rm -rf $backupPath");
    }

    // this prevents the following error:
    // Class \"Illuminate\\Console\\Events\\CommandFinished\" not found
    die();
  }

  public function sudo(): void
  {
    // do nothing
    // this will prevent loading of wire() which will prevent it
    // from trying to create cache files on shutdown
  }
}
