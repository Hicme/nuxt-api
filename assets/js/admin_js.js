function quickyLog(messages, title = 'Log', type = 'success') {
  this.timer;
  this.content = jQuery(`
  <div class="quickyLog__wrapper ${type}">
    <div class="quickyLog__top">
      <div class="quickyLog__top-title">${title}</div>
    </div>
    <div class="quickyLog__body">
      <div class="quickyLog__body_scroll">
        ${messages}
      </div>
    </div>
  </div>`);

  var ths = this;

  this.insert = function () {
    jQuery('body').append(ths.content);

    jQuery('.quickyLog__top', ths.content).on('click', function (e) {
      jQuery(this).parent().toggleClass('opened');
    });
    ths.startHide();
    ths.content.hover(function () {
      clearTimeout(ths.timer);
    }, function () {
        ths.startHide();
    });
  }

  this.startHide = function () {
    this.timer = setTimeout(function () {
      ths.content.fadeOut('normal', function () {
        ths.content.remove();
      });
    }.bind(this), 5000);
  }

  this.insert();
}