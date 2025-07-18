<?php

namespace RockMigrations;

use RockShell\Command;

class RmTransform extends Command
{
  public function config()
  {
    $this
      ->setDescription("Transform the projects folder structure to RockMigrations deployments")
      ->addOption('lazy', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Enable lazy (non-interactive) mode');
  }

  public function handle()
  {
    // Use rootPath for full project copy, docroot for shared assets
    $root = rtrim($this->app->rootPath(), "/");
    $docroot = rtrim($this->app->docroot(), "/");
    $lazy = $this->option('lazy') ? true : false;

    if (!$lazy && !$this->confirm("This will create a new folder structure in $root - continue?")) {
      return self::SUCCESS;
    }

    // backup current folder
    $name = 'backup-' . date('Y-m-d-His');
    $backupPath = dirname($root) . "/$name";
    $backup_ok=false;
    if ($lazy || $this->confirm("Backup current folder to $backupPath?", true)) {
      if (exec("cp -r $root $backupPath") === false) {
        $this->error("Backup failed!");
      } else {
        $this->success("Backup created at $backupPath.");
        $backup_ok=true;
      }
    }

    // cleanup
    exec("cd $root && rm -rf release-1");
    exec("cd $root && rm -rf current");
    exec("cd $root && rm -rf shared");
    $this->success("Old release/current/shared folders cleaned up.");

    // create folders
    $release = "$root/release-1";
    $shared = "$root/shared";
    exec("mkdir -p $release");
    exec("mkdir -p $shared");
    exec("cd $root && ln -snf release-1 current");
    $this->success("Folder structure reorganized.");

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
    $this->success("Shared assets copied.");

    // add symlinks for these files
    exec("cd $root && rm -rf $sitePath/assets/files && ln -snf ../../../shared/site/assets/files $sitePath/assets/files");
    exec("cd $root && rm -rf $sitePath/assets/backups && ln -snf ../../../shared/site/assets/backups $sitePath/assets/backups");
    exec("cd $root && rm -rf $sitePath/config-local.php && ln -snf ../../shared/site/config-local.php $sitePath/config-local.php");
    $this->success("Symlinks for shared assets created.");

    // remove backup folder?
    if ($backup_ok && ($lazy || $this->confirm("Remove backup folder $backupPath?"))) {
      if (exec("rm -rf $backupPath") === false) {
        $this->error("Failed to remove backup folder $backupPath!");
      } else {
        $this->success("Backup removed.");
      }
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
