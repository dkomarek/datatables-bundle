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

        state = (state.length > 1 ? deparam(state.substr(1)) : {});

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
                dt.on('draw.dt', function(e) {
                    var data = $.param(dt.state()).split('&');

                    // First draw establishes state, subsequent draws run diff on the first
                    if (!baseState) {
                        baseState = data;
                    } else {
                        console.log(dt.state());
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

                    if (searchCol.sSearch.length === 0) {
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

    /**
     * Server-side export.
     */
    $.fn.initDataTables.exportBtnAction = function(exporterName, settings) {
        settings = $.extend({}, $.fn.initDataTables.defaults, settings);

        return function(e, dt) {
            const params = $.param($.extend({}, dt.ajax.params(), {'_dt': settings.name, '_exporter': exporterName}));

            // Credit: https://stackoverflow.com/a/23797348
            const xhr = new XMLHttpRequest();
            xhr.open(settings.method, settings.method === 'GET' ? (settings.url + '?' +  params) : settings.url, true);
            xhr.responseType = 'arraybuffer';
            xhr.onload = function () {
                if (this.status === 200) {
                    let filename = "";
                    const disposition = xhr.getResponseHeader('Content-Disposition');
                    if (disposition && disposition.indexOf('attachment') !== -1) {
                        const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                        const matches = filenameRegex.exec(disposition);
                        if (matches != null && matches[1]) {
                            filename = matches[1].replace(/['"]/g, '');
                        }
                    }

                    const type = xhr.getResponseHeader('Content-Type');

                    let blob;
                    if (typeof File === 'function') {
                        try {
                            blob = new File([this.response], filename, { type: type });
                        } catch (e) { /* Edge */ }
                    }

                    if (typeof blob === 'undefined') {
                        blob = new Blob([this.response], { type: type });
                    }

                    if (typeof window.navigator.msSaveBlob !== 'undefined') {
                        // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
                        window.navigator.msSaveBlob(blob, filename);
                    }
                    else {
                        const URL = window.URL || window.webkitURL;
                        const downloadUrl = URL.createObjectURL(blob);

                        if (filename) {
                            // use HTML5 a[download] attribute to specify filename
                            const a = document.createElement("a");
                            // safari doesn't support this yet
                            if (typeof a.download === 'undefined') {
                                window.location = downloadUrl;
                            }
                            else {
                                a.href = downloadUrl;
                                a.download = filename;
                                document.body.appendChild(a);
                                a.click();
                            }
                        }
                        else {
                            window.location = downloadUrl;
                        }

                        setTimeout(function() { URL.revokeObjectURL(downloadUrl); }, 100); // cleanup
                    }
                }
            };

            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.send(settings.method === 'POST' ? params : null);
        }
    };

    /**
     * Convert a querystring to a proper array - reverses $.param
     */
    function deparam(params, coerce) {
        var obj = {},
            coerce_types = {'true': !0, 'false': !1, 'null': null};
        $.each(params.replace(/\+/g, ' ').split('&'), function (j, v) {
            var param = v.split('='),
                key = decodeURIComponent(param[0]),
                val,
                cur = obj,
                i = 0,
                keys = key.split(']['),
                keys_last = keys.length - 1;

            if (/\[/.test(keys[0]) && /\]$/.test(keys[keys_last])) {
                keys[keys_last] = keys[keys_last].replace(/\]$/, '');
                keys = keys.shift().split('[').concat(keys);
                keys_last = keys.length - 1;
            } else {
                keys_last = 0;
            }

            if (param.length === 2) {
                val = decodeURIComponent(param[1]);

                if (coerce) {
                    val = val && !isNaN(val) ? +val              // number
                        : val === 'undefined' ? undefined         // undefined
                            : coerce_types[val] !== undefined ? coerce_types[val] // true, false, null
                                : val;                                                // string
                }

                if (keys_last) {
                    for (; i <= keys_last; i++) {
                        key = keys[i] === '' ? cur.length : keys[i];
                        cur = cur[key] = i < keys_last
                            ? cur[key] || (keys[i + 1] && isNaN(keys[i + 1]) ? {} : [])
                            : val;
                    }

                } else {
                    if ($.isArray(obj[key])) {
                        obj[key].push(val);
                    } else if (obj[key] !== undefined) {
                        obj[key] = [obj[key], val];
                    } else {
                        obj[key] = val;
                    }
                }

            } else if (key) {
                obj[key] = coerce
                    ? undefined
                    : '';
            }
        });

        return obj;
    }
}(jQuery));
