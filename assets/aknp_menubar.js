jQuery(document).ready(function ($) {

    let selector = window.aknp_admin_vars.nav_id
    const targetElm = 'aknp_search_box';
    $('#' + selector).on('click', function () {
        modalInit();
    })
    $(document).keydown(function (e) {
        if (e.key === 'k' && e.ctrlKey === true) {

            modalInit()
            e.preventDefault();

        } else if (e.key === 'Escape') {
            hideModal();
        }

    });
    $(document).on("click", ".aknp_modal-close", function () {
        hideModal();
    });


    function modalInit() {
        $('.aknp_modal').toggleClass('is-visible');
        $('#' + targetElm).focus();
        setTimeout(function () {
            $('#' + targetElm)[0].focus()
        }, 500);
        // prevent the focus from leaving
    }

    var items = window.aknp_admin_vars.menus
    jQuery('#' + targetElm).autocomplete({
        source: items,
        minLength: 2,
        select: function (event, ui) {
            event.preventDefault();

            const link = jQuery(ui.item.label).attr('href');
            window.location.href = link;

        },
        classes: {
            "ui-autocomplete": "aknp-search-dropdown"
        },
        appendTo: "#aknp_modal-elm",
        change: function (event, ui) {
            const $element = jQuery(event.target);
        },
        autoFocus: true

    }).data("ui-autocomplete")._renderItem = function (ul, item) {
        return $("<li></li>")
            .data("item.autocomplete", item.label)
            .append(item.label)
            .appendTo(ul);
    };


    function hideModal(e) {
        $('.aknp_modal').removeClass('is-visible');
    }

    addShortcutKeyToMenus()

    function addShortcutKeyToMenus(){
        const menus = jQuery('#adminmenu .wp-menu-name');
        $.each(menus,function( index, menuItem ) {

            let key = String.fromCharCode(65+index);
            let shortcutKey = `<span class="aknp_shortcut"> ${key} </span>`
            $(menuItem).append(shortcutKey)

            $(document).keydown(function (e) {
                if ((e.key.toLowerCase()  === key.toLowerCase())  && e.ctrlKey === false) {
                    if (e.target.tagName != 'INPUT'){
                        $(menuItem).trigger('click');
                    }
                }
            });
        });
    }
    
});


