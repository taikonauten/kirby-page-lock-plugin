
(function($) {

  'use strict';

  var pageLock = {
    init: function() {
      this.editingPageUrl = null;
      this.pageUrlsBeingEdited = [];
      this.pingInterval = null;
    },
    onPanelStartEditingPage: function(editingPageUrl) {
      this.editingPageUrl = editingPageUrl;
      this.pageUrlsBeingEdited = [];
      this.pingInterval = setInterval(this.ping.bind(this), 10000);
    },
    onPanelPageChange: function() {
      this.editingPageUrl = null;

      // clear ping interval
      if (this.pingInterval !== null) {
        clearInterval(this.pingInterval);
        this.pingInterval = null;
      }
    },
    ping: function() {
      $.ajax({
        data: {
          pagelock: 1,
          pageurl: this.editingPageUrl
        }
      }).success(function(data) {
        if (data.success === true) {
          this.setPageUrlsBeingEdited(data.pageUrlsBeingEdited);
        }
      }.bind(this));
    },
    setPageUrlsBeingEdited: function(pageUrlsBeingEdited) {
      var i, pageUrl;

      for (i = 0; i < pageUrlsBeingEdited.length; i++) {
        pageUrl = pageUrlsBeingEdited[i];
        if (this.pageUrlsBeingEdited.indexOf(pageUrl) === -1) {
          this.onPageBeingEdited(pageUrl);
        }
      }

      for (i = 0; i < this.pageUrlsBeingEdited.length; i++) {
        pageUrl = this.pageUrlsBeingEdited[i];
        if (pageUrlsBeingEdited.indexOf(pageUrl) === -1) {
          this.onPageStoppedBeingEdited(pageUrl);
        }
      }

      this.pageUrlsBeingEdited = pageUrlsBeingEdited;
    },
    onPageBeingEdited: function(pageUrl) {
      $('a[href$="/panel/pages/' + pageUrl + '/edit"] > span')
        .append(' <i class="page-lock fa fa-lock"></i>');
    },
    onPageStoppedBeingEdited: function(pageUrl) {
      $('a[href$="/panel/pages/' + pageUrl + '/edit"] .page-lock')
        .remove();
    },
    alert: function(message) {

      // wait for the dom content to be integrated
      setTimeout(function() {

        // create message element
        var $message = $('<div>')
          .addClass('message message-is-alert message-required')
          .append(
            $('<span>')
              .addClass('message-content')
              .text(message),
            $('<a>')
              .addClass('message-toggle')
              .append('<i>×</i>')
          );

        // append it to topbar
        var $topbar = $('header.topbar');
        $topbar.append($message);

      }, 1000);
    }
  };

  // export page lock object as a global var
  window.__pageLock = pageLock;

  // init page lock
  pageLock.init();

})(jQuery);
