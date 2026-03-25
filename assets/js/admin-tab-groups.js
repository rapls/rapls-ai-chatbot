jQuery(document).ready(function($) {
    // Group tab switching
    $('.raplsaich-tab-group').on('click', function(e) {
        e.preventDefault();
        var group = $(this).data('group');

        // Update group tabs
        $('.raplsaich-tab-group').removeClass('raplsaich-tab-group-active');
        $(this).addClass('raplsaich-tab-group-active');

        // Show/hide sub-tabs
        $('.raplsaich-sub-tabs').hide();
        $('.raplsaich-sub-tabs[data-for="' + group + '"]').show();

        // Activate first sub-tab in group
        var $activeSubTab = $('.raplsaich-sub-tabs[data-for="' + group + '"] .raplsaich-sub-tab-active');
        if (!$activeSubTab.length) {
            $activeSubTab = $('.raplsaich-sub-tabs[data-for="' + group + '"] .raplsaich-sub-tab:first');
        }
        $activeSubTab.trigger('click');
    });

    // Sub-tab switching
    $('.raplsaich-sub-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');

        // Update sub-tab active state
        $(this).closest('.raplsaich-sub-tabs').find('.raplsaich-sub-tab').removeClass('raplsaich-sub-tab-active');
        $(this).addClass('raplsaich-sub-tab-active');

        // Update tab content
        $('.raplsaich-pro-preview .tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });
});
