/**
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */

(function($) {
    /**
     * Initializes the datatable dynamically.
     */
    $.fn.initDataTables = function(config, options) {

        //Update default used url, so it reflects the current location (useful on single side apps)
        $.fn.initDataTables.defaults.url = window.location.origin + window.location.pathname;

        var root = this,
            config = $.extend({}, $.fn.initDataTables.defaults, config),
            state = ''
        ;

        var searchTimeout;

        // Load page state if needed
        switch (config.state) {
            case 'fragment':
                state = window.location.hash;
                break;
            case 'query':
                state = window.location.search;
                break;
        }

        //state = (state.length > 1 ? deparam(state.substr(1)) : {});
        var persistOptions = config.state === 'none' ? {} : {
            stateSave: true,
            stateLoadCallback: function(s, cb) {
                // Only need stateSave to expose state() function as loading lazily is not possible otherwise
                return null;
            }
        };

        const highlightFilterField = function(field) {
            let $field = $(field);

            // for Select2 element
            if ($field.hasClass("select2-hidden-accessible")) {
                $field = $field.next(".select2").find(".select2-selection");
            }

            if (field.value !== '') {
                $field.addClass(config.filterActiveClass);
            } else {
                $field.removeClass(config.filterActiveClass);
            }
        };

        // column field filtering
        const searchByField = function(field, dt) {
            const column = dt.column($(field).data("filter-index"));
            if (column.search() !== field.value) {
                highlightFilterField(field);

                if (searchTimeout) { clearTimeout(searchTimeout); }
                searchTimeout = setTimeout(function () {
                    column
                        .search(field.value)
                        .draw();
                }, config.filterTimeout);
            }
        };

        return new Promise((fulfill, reject) => {
            var baseState;

            // Merge all options from different sources together and add the Ajax loader
            var dtOpts = $.extend({}, typeof config.options === 'function' ? {} : config.options, options, persistOptions, {
                ajax: function(request, drawCallback, settings) {
                    request._dt = config.name;
                    $.ajax(typeof config.url === 'function' ? config.url(dt) : config.url, {
                        method: config.method,
                        data: request
                    }).done(function(data) {
                        drawCallback(data);
                        fulfill(dt);
                    }).fail(function(xhr, cause, msg) {
                        console.error('DataTables request failed: ' + msg);
                        reject(cause);
                    });
                }
            });

            if (typeof config.options === 'function') {
                dtOpts = config.options(dtOpts);
            }

            root.html(config.template);
            dt = $('table', root).DataTable(dtOpts);
            if (config.state !== 'none') {
                dt.on('draw.dt', function() {
                    var data = $.param(dt.state()).split('&');

                    // First draw establishes state, subsequent draws run diff on the first
                    if (!baseState) {
                        baseState = data;
                    } else {
                        var diff = data.filter(el => {
                            return (baseState.indexOf(el) === -1 && el.indexOf('time=') !== 0 && el.indexOf('childRows') !== 0)
                                || el.indexOf('_dt=') === 0;
                        });
                        switch (config.state) {
                            case 'fragment':
                                history.replaceState(null, null, window.location.origin + window.location.pathname + window.location.search
                                    + '#' + decodeURIComponent(diff.join('&')));
                                break;
                            case 'query':
                                history.replaceState(null, null, window.location.origin + window.location.pathname
                                    + '?' + decodeURIComponent(diff.join('&') + window.location.hash));
                                break;
                        }
                    }
                });
            }

            // init filters highlight
            for (let columnIndex in dtOpts.searchCols){
                if (dtOpts.searchCols.hasOwnProperty(columnIndex)) {
                    const searchCol = dtOpts.searchCols[columnIndex];

                    if (searchCol === null || searchCol.sSearch.length === 0) {
                        continue;
                    }

                    const $filterField = $("[data-filter-index=" + columnIndex + "]", "#" + config.filterHtmlId);
                    if ($filterField.length > 0) {
                        highlightFilterField($filterField.get(0));
                    }
                }
            }

            // external filters handlers
            $("input.datatable-filter", "#" + config.filterHtmlId).on("keyup change clear", function () {
                searchByField(this, dt);
            });

            $("select.datatable-filter", "#" + config.filterHtmlId).on("change clear", function () {
                searchByField(this, dt);
            });
        });
    };

    /**
     * Provide global component defaults.
     */
    $.fn.initDataTables.defaults = {
        method: 'POST',
        state: 'query',
        filterActiveClass: 'bg-success bg-opacity-25',
        filterTimeout: 1000,
        url: window.location.origin + window.location.pathname
    };
}(jQuery));
