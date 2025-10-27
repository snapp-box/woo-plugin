jQuery(document).ready(function($){
    function getActiveDayContainer() {
        var day = $('#snappbox-day').val();
        var $container = $('#snappbox-time-slots').find('[data-day="'+day+'"]');
        if ($container.length === 0) {
            $container = $('<div class="snappbox-day-slots" data-day="'+day+'"></div>');
            $('#snappbox-time-slots').append($container);
        }
        return $container;
    }

    $('#snappbox-day').on('change', function() {
        var selectedDay = $(this).val();
        $('.snappbox-day-slots').hide();
        var $visible = $('[data-day="'+selectedDay+'"]');
        if ($visible.length === 0) {
            $visible = $('<div class="snappbox-day-slots" data-day="'+selectedDay+'"></div>');
            $('#snappbox-time-slots').append($visible);
        }
        $visible.show();
    }).trigger('change');

    $('#add-time-slot').on('click', function() {
        var $container = getActiveDayContainer();
        var $slot = $('<div class="time-slot">'+
            '<input type="time" class="start" /> '+
            '<input type="time" class="end" /> '+
            '<button type="button" class="remove-slot button" aria-label="Remove slot">'+
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="16" height="16" aria-hidden="true">'+
                    '<path fill="#f44336" d="M44,24c0,11.045-8.955,20-20,20S4,35.045,4,24S12.955,4,24,4S44,12.955,44,24z"/>'+
                    '<path fill="#fff" d="M29.656,15.516l2.828,2.828l-14.14,14.14l-2.828-2.828L29.656,15.516z"/>'+
                    '<path fill="#fff" d="M32.484,29.656l-2.828,2.828l-14.14-14.14l2.828-2.828L32.484,29.656z"/>'+
                '</svg>'+
            '</button>'+
        '</div>');
        $container.append($slot);
    });

    $(document).on('click', '.remove-slot', function() {
        $(this).closest('.time-slot').remove();
    });

    $('#save-snappbox-schedule').on('click', function() {
        var day = $('#snappbox-day').val();
        var $container = getActiveDayContainer();
        var slots = [];

        $container.find('.time-slot').each(function() {
            slots.push({
                start: $(this).find('.start').val(),
                end: $(this).find('.end').val()
            });
        });

        $('.selection-wrapper .notice').remove();

        $.post(SnappBoxData.ajax_url, {
            action: 'save_snappbox_schedule',
            nonce: SnappBoxData.nonce,
            day: day,
            slots: JSON.stringify(slots)
        }, function(response) {
            if (response && response.success) {
                var msg = (response.data && response.data.message) ? response.data.message : 'Saved.';
                $('.selection-wrapper').prepend('<div class="notice notice-success is-dismissible"><p>'+msg+'</p></div>');
            } else {
                var err = (response && response.data && response.data.message) ? response.data.message : 'Error.';
                $('.selection-wrapper').prepend('<div class="notice notice-error is-dismissible"><p>'+err+'</p></div>');
            }
        }).fail(function() {
            $('.selection-wrapper').prepend('<div class="notice notice-error is-dismissible"><p>Request failed.</p></div>');
        });
    });
});
