/*
 * banner_warn plugin
 * @author pulsejet
 */

var banner_warn = {
    insertrow: function(evt) {
        // Check if we have the required data
        if (!rcmail.env.banner_avatar || !rcmail.env.banner_avatar[evt.uid]) return;

        // Get object
        const obj = rcmail.env.banner_avatar[evt.uid];

        // Border for warning the user
        const warn = obj.warn ? 'warn ' : '';
        const calert = obj.alert ? 'alert ' : '';

        // Get image avatar
        const showImages = rcmail.env.banner_avatar_images;
        const image = (warn || calert || !showImages) ? '' : './?_task=addressbook&_action=photo&_email=' + obj.from + '&_error=1';

        // Add column of avatar
        $('td.subject', evt.row.obj).before(
            $('<td/>', { class: 'banner-warn' }).append(
                $('<div />', { class: 'avatar ' + warn + calert }).append(
                    $('<img />', { src: image, alt: '' }).on('error', function () {
                        $(this).replaceWith($('<span />').html(obj.name));
                    }).on('load', function () {
                        $(this).css('visibility', 'visible');
                    }).css('visibility', 'hidden')
                ).append(
                    $('<span />', { class: 'tick' }).html('&#10003;')
                ).css('color', '#' + obj.color)
            ).on('mousedown', function (event) {
                rcmail.message_list.select_row(evt.uid, CONTROL_KEY, true);
                event.stopPropagation();
            }).on('touchstart', function (event) {
                event.stopPropagation();
            })
        );

        // Add column of avatar if does not exit
        if ($('th.banner-warn').length === 0 && $('th.subject').length > 0) {
            $('th.subject').before(
                $('<th/>', { class: 'banner-warn' })
            );
        }
    }
};

rcube_webmail.prototype.markasknown_mark = function(is_known, _sender) {
    var uids = this.env.uid ? [this.env.uid] : this.message_list.get_selection();
    // console.log(uids)
    if (!uids)
        return;

    var senders = []
    uids.forEach((uid) => {
        if (rcmail.env.banner_avatar !== undefined) {
            var sender = rcmail.env.banner_avatar[uid]['from']
            // console.log(sender)
            senders.push(sender)
        }
        else {
            senders.push(_sender)
        }
    })
    
    
    var lock = this.set_busy(true, 'loading');
    // console.log(lock)
    this.http_post('plugin.markasknown.' + (is_known ? 'known' : 'unknown'), this.selection_post_data({_uid: uids, _senders: senders}), lock);
}

window.rcmail && rcmail.addEventListener('init', function(evt) {
        rcmail.register_command('plugin.markasknown.known', function() { rcmail.markasknown_mark(true); }, rcmail.env.uid);
        rcmail.register_command('plugin.markasknown.unknown', function() { rcmail.markasknown_mark(false); }, rcmail.env.uid);
        $("#markasknown").find('span').text((index, currentcontent) => {
            return currentcontent.replace(/[\[\]']+/g,'')
        })
        $("#markasunknown").find('span').text((index, currentcontent) => {
            return currentcontent.replace(/[\[\]']+/g,'')
        })

        if (rcmail.gui_objects.messagelist) {
            rcmail.addEventListener('insertrow', banner_warn.insertrow);

            const _hrow = rcmail.message_list.highlight_row.bind(rcmail.message_list);
            rcmail.message_list.highlight_row = function(...args) {
                if (args[1]) {
                    $(rcmail.message_list.tbody).addClass('multiselect');
                } else {
                    $(rcmail.message_list.tbody).removeClass('multiselect');
                }
                _hrow(...args);
            }

            rcmail.message_list.addEventListener('select', function(list) {
                rcmail.enable_command('plugin.markasknown.known', list.get_selection(false).length > 0);
                rcmail.enable_command('plugin.markasknown.unknown', list.get_selection(false).length > 0);

                if (list.get_selection(false).length > 0) {
                    $("#markasknown").addClass("active")
                    $("#markasunknown").addClass("active")
                }
                else {
                    $("#markasknown").removeClass("active")
                    $("#markasunknown").removeClass("active")
                }
            });
        }

        /* yes/no button for recognizing sender */
        $('.yes-button').click(function() {
            rcmail.markasknown_mark(true, $('.yes-button').attr('sender'));
        });
        $('.no-button').click(function() {
            rcmail.markasknown_mark(false, $('.no-button').attr('sender'));
        });
});
