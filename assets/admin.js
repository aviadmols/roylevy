jQuery(function($) {
    const button = $('#rle-fetch-event');
    const source = $('#rle_source_url');
    const ticket = $('#rle_ticket_url');
    const status = $('#rle-fetch-status');
    let previousSource = source.val().trim();

    source.on('input change', function() {
        const url = source.val().trim();
        const currentTicket = ticket.val().trim();

        if (!currentTicket || currentTicket === previousSource) {
            ticket.val(url);
        }

        previousSource = url;
    });

    button.on('click', function() {
        const url = source.val().trim();

        if (!url) {
            status.removeClass('rle-status-success').addClass('rle-status-error').text('יש להזין לינק לאירוע.');
            return;
        }

        ticket.val(url);
        button.prop('disabled', true).text('טוען...');
        status.removeClass('rle-status-success rle-status-error').text('מושך נתונים מהעמוד...');

        $.post(RLEAdmin.ajaxUrl, {
            action: 'rle_fetch_event',
            nonce: RLEAdmin.nonce,
            url: url
        }).done(function(response) {
            if (!response || !response.success || !response.data) {
                status.removeClass('rle-status-success').addClass('rle-status-error').text('לא התקבל מידע תקין.');
                return;
            }

            if (response.data.title) {
                if ($('#title').length) {
                    $('#title').val(response.data.title).trigger('input');
                }

                if (window.wp && wp.data && wp.data.dispatch) {
                    try {
                        wp.data.dispatch('core/editor').editPost({ title: response.data.title });
                    } catch (e) {
                    }
                }
            }

            if (response.data.date) {
                $('#rle_date').val(response.data.date);
            }

            if (response.data.time) {
                $('#rle_time').val(response.data.time);
            }

            if (response.data.location) {
                $('#rle_location').val(response.data.location);
            }

            if (response.data.venue) {
                $('#rle_venue').val(response.data.venue);
            }

            ticket.val(url);
            status.removeClass('rle-status-error').addClass('rle-status-success').text('המידע נמשך בהצלחה ולינק הרכישה עודכן.');
        }).fail(function(xhr) {
            let message = 'הפעולה נכשלה.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            status.removeClass('rle-status-success').addClass('rle-status-error').text(message);
        }).always(function() {
            button.prop('disabled', false).text('משוך מידע מהלינק');
        });
    });
});
