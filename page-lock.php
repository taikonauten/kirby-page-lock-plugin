<?php

if (!class_exists('Kirby\Panel')) {
  // ignore this plugin on frontend
  return;
}

if (kirby()->site()->user() === false) {
  // ignore this plugin when not logged in
  return;
}

class PageLockPlugin {

  protected $kirby;
  protected $panel;
  protected $user;

  protected $editingLog;
  protected $editingLogPath;
  protected $cooldown;

  public function __construct($kirby, $panel) {
    $this->kirby = $kirby;
    $this->panel = $panel;
    $this->user = $this->kirby->site()->user()->current();

    $this->editingLog = null;
    $this->editingLogPath = c::get(
      'plugin.pagelock.editinglogpath',
      $this->kirby->roots()->cache() . DS . 'page-lock.json'
    );

    $this->cooldown = c::get('plugin.pagelock.cooldown', 20);

    // trigger startup event
    $this->handleBeforeStartup();
  }

  public function handleBeforeStartup() {

    // check if this is a page lock ping
    if (isset($_GET['pagelock'])) {
      $editingPageUrl = $_GET['pageurl'];
      $this->handlePageLockPing($editingPageUrl);

      header('Content-type: application/json');
      echo json_encode([
        'success' => true,
        'pageUrlsBeingEdited' => $this->getPageUrlsBeingEdited(),
      ]);
      die();
    }

    // register shutdown function
    register_shutdown_function([$this, 'handlePostRendering']);

    // start collecting output
    ob_start();
  }

  public function handlePageLockPing($pageUrl) {
    $editingLog = $this->getPageEditingLog($pageUrl);

    // add or replace last access timestamp entry for this user
    $editingLog[$this->user->username()] = time();

    $this->setPageEditingLog($pageUrl, $editingLog);
  }

  public function handlePostRendering() {
    // do nothing on non-GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      return;
    }

    // collect data
    $output = ob_get_contents();
    $route = $this->panel->router->route();

    $isAjaxRequest = (strpos(
      implode(';', headers_list()),
      'Content-type: application/json') !== false);

    // this variable contains js that will be injected
    // at the end of the html code of the current response
    $scriptInjection = '';

    if ($isAjaxRequest) {
      // inject script that clears the ping interval
      $scriptInjection .= 'pl.onPanelPageChange();';
    }

    // inject script to update page urls being edited
    $scriptInjection .= sprintf('pl.setPageUrlsBeingEdited(%s);',
      json_encode($this->getPageUrlsBeingEdited()));

    // check if user is editing a page
    if ($route->action === 'PagesController::edit') {
      // the first arg contains the url of the page being edited
      $editingPageUrl = $route->arguments[0];

      // render ping script from template injecting the page url
      $scriptInjection .= sprintf('pl.onPanelStartEditingPage(%s);',
        json_encode($editingPageUrl));

      // render alert notifing the user of other potential users on the same
      // page, if there are any
      $editingUsers = $this->getPageEditingUsers($editingPageUrl);
      if ($alert = $this->composeEditingAlert($editingUsers)) {
        $scriptInjection .= sprintf('pl.alert(%s);',
          json_encode($alert));
      }
    }

    if (!empty($scriptInjection)) {

      // wrap js script into an html tag
      $htmlInjection = sprintf(
        "<script>(function(pl){'use strict';%s})(window.__pageLock);</script>",
        $scriptInjection);

      // inject script tag into response
      if ($isAjaxRequest) {
        try {
          $outputArray = json_decode($output, true);
          if (isset($outputArray['content'])) {
            $outputArray['content'] .= $htmlInjection;
          }
          $output = json_encode($outputArray);
        } catch (Exception $e) {
        }
      } else {
        // inject the page lock script
        $htmlInjection = sprintf(
          '<script src="%s"></script>%s',
          url('/assets/plugins/page-lock/js/page-lock.js'),
          $htmlInjection
        );

        if (($pos = strpos($output, '</body>')) !== false) {
          $output = substr_replace($output, $htmlInjection, $pos, 0);
        }
      }
    }

    // exchange output
    ob_end_clean();
    echo $output;
  }

  public function composeEditingAlert($editingUsers) {
    if (count($editingUsers) === 0) {
      return null;
    }

    $names = array_map(function($user) {
      // create display name for this user
      if (!empty($user->firstname()) && !empty($user->lastname())) {
        return $user->firstname() . ' ' . $user->lastname();
      }
      return $user->username();
    }, $editingUsers);

    // single user message
    if (count($names) === 1) {
      return sprintf('%s is currently editing this page', $names[0]);
    }

    // multiple users message
    $lastName = array_pop($names);
    return sprintf(
      '%s and %s are currently editing this page',
      implode(', ', $names),
      $lastName);
  }

  public function getPageEditingUsers($pageUrl) {
    $editingLog = $this->getPageEditingLog($pageUrl);
    $users = [];

    foreach ($editingLog as $username => $accessTimestamp) {
      if (
        $accessTimestamp >= time() - $this->cooldown &&
        $username !== $this->user->username()
      ) {
        $user = $this->kirby->site->users()->get($username);
        if ($user) {
          array_push($users, $user);
        }
      }
    }
    return $users;
  }

  public function getPageUrlsBeingEdited() {
    $editingLog = $this->getEditingLog();
    $pageUrls = [];
    foreach ($editingLog as $pageUrl => $pageEditingLog) {
      $lastEditTime = 0;
      foreach ($pageEditingLog as $username => $editTime) {
        if ($username !== $this->user->username()) {
          $lastEditTime = max($lastEditTime, $editTime);
        }
      }
      if ($lastEditTime >= time() - $this->cooldown) {
        array_push($pageUrls, $pageUrl);
      }
    }
    return $pageUrls;
  }

  public function getPageEditingLog($pageUrl) {
    $editingLog = $this->getEditingLog();
    if (isset($editingLog[$pageUrl])) {
      return $editingLog[$pageUrl];
    }
    return [];
  }

  public function setPageEditingLog($pageUrl, $pageEditingLog) {
    $editingLog = $this->getEditingLog();
    $editingLog[$pageUrl] = $pageEditingLog;
    return $this->setEditingLog($editingLog);
  }

  protected function getEditingLog() {
    if ($this->editingLog === null) {
      if (file_exists($this->editingLogPath)) {
        try {
          $editingLogRaw = file_get_contents($this->editingLogPath);
          $this->editingLog = json_decode($editingLogRaw, true);
        } catch (Exception $e) {
          // continue with empty log
        }
      } else {
        // create empty log
        $this->editingLog = [];
      }
    }
    return $this->editingLog;
  }

  protected function setEditingLog($editingLog) {
    // remove old entries
    foreach ($editingLog as $pageUrl => $pageEditingLog) {
      $lastEditTime = 0;
      foreach ($pageEditingLog as $username => $editTime) {
        $lastEditTime = max($lastEditTime, $editTime);
      }

      // remove entries that are older than 1 day
      if ($lastEditTime < time() - 24 * 60 * 60) {
        unset($editingLog[$pageUrl]);
      }
    }

    // update cached value
    $this->editingLog = $editingLog;

    // save editing log
    file_put_contents($this->editingLogPath, json_encode($editingLog));
    return $this;
  }
}

// create plugin instance
$instance = new PageLockPlugin(kirby(), Kirby\Panel::instance());
