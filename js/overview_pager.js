Drupal.behaviors.overview_pager = {
  attach: function (context, settings) {
    jQuery('.overview-form input', context).keydown(function(e) {
      if (e.keyCode == 13) {
        e.stopPropagation();
        e.preventDefault();
        jQuery(e.target).blur();
      }
    });
    jQuery('.overview-form-contents .pager .pager__item a', context).click(function(e) {
      e.preventDefault();
      let searchParams = new URLSearchParams(jQuery(this).attr('href'));
      let form = jQuery(this).closest('.overview-form');
      let submit = form.find('.overview-page-submit');
      let page = form.find('.overview-page-value');
      page.val(searchParams.get('page'));
      submit.trigger('click');
    });
    jQuery('.overview-page-value').val('0');
  }
};
