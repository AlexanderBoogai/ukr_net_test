
//Паттерн названия айтема
namePattern = new RegExp("^(.){1,100}$");
//Паттерн дробных чисел
floatPattern = new RegExp("^[0-9]+(\.[0-9]{1,2})?$");

//Получает массив выходных
function runHollydayDP() {
    var csrf = $('input[name = "_token"]').val();
    var _today = new Date();
    var timezone_diff = $("#company_timezone_diff_in_minutes").val();
    var user_date = new Date(
        new Date((_today.getTime() + (_today.getTimezoneOffset() * 60000)) + (timezone_diff * 60000))
    );
    var user_dateString = user_date.getFullYear() + "-" + user_date.getMonth() + "-" + user_date.getDate();
    //Holidays
    $.ajax({
        url: '/holiday/ajax-show',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf
        }
    }).done(function (data) {
        holidayDates = $.parseJSON(data);
        $("#holidays_datepicker").datepicker({
            //Формат даты возвращаемой датапикером
            dateFormat: "mm/dd/yy",
            defaultDate: user_date,
            //Пробегает по всем числам которые выводятся на экран
            beforeShowDay: function (date) {
                //date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                /*console.log(date.getMinutes());
                 console.log(date.getTimezoneOffset());*/
                //Обычная дата
                var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
                //Дата для ежегодного праздника
                var anualDateString = "0001-" + date.getMonth() + "-" + date.getDate();

                var h = holidayDates.dates[dateString];
                var a = holidayDates.dates[anualDateString];
                if (dateString == user_dateString) {
                    var today = 1;
                } else {
                    var today = 0;
                }

                //Если в массиве выходных есть данная дата
                if (h || a || today) {
                    //Строка с возвращаемыми классами
                    var classString = "";
                    //Если это одноразовый выходной
                    if (h) {
                        if (holidayDates.dates[dateString]['type'] == "past") {
                            classString += " past-holiday ";
                        } else {
                            classString += " following-holiday ";
                        }
                    }

                    //Если это ежегодный выходной
                    if (a) {
                        //Формирует значение сегодняшнего дня в секундах
                        var now = new Date();
                        /*console.log(now.getMinutes());
                         console.log(now.getTimezoneOffset());*/
                        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).valueOf();
                        //Если дата которая пригла с datepicker'a меньше чем сегодняшняя
                        if (date.valueOf() < today) {
                            classString += " past-anual ";
                        } else {
                            //Если больше
                            classString += " following-anual ";
                        }
                    }

                    if (today) {
                        classString += " company-today"
                    }

                    // if it is return the following.
                    return [true, classString, ''];
                } else {
                    // default
                    return [true, '', ''];
                }
            },
            //Срабатывает при клике на дату в календаре
            onSelect: function (dateText, inst) {
                //Создание обьекта даты из информации которую вернул datepicker
                var date = new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay);
                //Формирование строки даты для одиночного выходного
                var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
                //Формирование строки даты для одиночного выходного, пользовательская(+1)
                var dateStringUser = date.getDate() + "/" + (date.getMonth() + 1) + "/" + date.getFullYear();
                //Формирование строки даты для ежегодного выходного
                var anualDateString = "0001-" + date.getMonth() + "-" + date.getDate();
                //Формирование строки даты для ежегодного выходного, пользовательская(+1)
                var anualDateStringUser = date.getDate() + "/" + (date.getMonth() + 1);
                var h = holidayDates.dates[dateString];
                var a = holidayDates.dates[anualDateString];
                //Если в массиве выходных есть данная дата
                if (h || a) {
                    if (h) {
                        //Если это одноразовый выходной
                        $(".old-holiday-desc").val(holidayDates.dates[dateString]['desc']);
                        $(".old-holiday-date").val(dateString);
                        $(".holiform_date").html(dateStringUser);
                        $(".holiform_type").html('Single');
                        $(".old-holiday").show(200);

                    } else if (a) {
                        //Если это ежегодный выходной
                        $(".old-holiday-desc").val(holidayDates.dates[anualDateString]['desc']);
                        $(".old-holiday-date").val(anualDateString);
                        $(".holiform_date").html(anualDateStringUser + ' <span class="desc_anual">(every year)</span>');
                        $(".holiform_type").html('Anual');
                        $(".old-holiday").show(200);
                    }
                }
                //Если в массиве нету данной даты
                else {
                    $(".new-holiday-desc").val("");
                    $("#radio_single").prop("checked", true);
                    $(".new-holiday-date").val(dateString);
                    $(".holiform_date").html(dateStringUser);

                    $(".new-holiday").show(200);
                }
            }
        });
    });
}

function runPeakDP() {
    var csrf = $('input[name = "_token"]').val();

    var _today = new Date();
    var timezone_diff = $("#company_timezone_diff_in_minutes").val();
    var user_date = new Date(
        new Date((_today.getTime() + (_today.getTimezoneOffset() * 60000)) + (timezone_diff * 60000))
    );
    var user_dateString = user_date.getFullYear() + "-" + user_date.getMonth() + "-" + user_date.getDate();

    //Peaks
    $.ajax({
        url: '/peak/ajax-show',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf
        }
    }).done(function (data) {
        peakDates = $.parseJSON(data);
        $("#peaks_datepicker").datepicker({
            //Формат даты возвращаемой датапикером
            dateFormat: "mm/dd/yy",
            defaultDate: user_date,
            //Пробегает по всем числам которые выводятся на экран
            beforeShowDay: function (date) {
                //Обычная дата
                var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
                var h = peakDates.dates[dateString];
                if (dateString == user_dateString) {
                    var today = 1;
                } else {
                    var today = 0;
                }

                //Если в массиве выходных есть данная дата
                if (h || today) {
                    if (h) {
                        //Строка с возвращаемыми классами
                        var classString = "peak-day";

                    }
                    if (today) {
                        classString += " company-today"
                    }
                    // if it is return the following.
                    return [true, classString, ''];
                } else {
                    // default
                    return [true, '', ''];
                }
            },
            //Срабатывает при клике на дату в календаре
            onSelect: function (dateText, inst) {
                //Создание обьекта даты из информации которую вернул datepicker
                var date = new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay);
                //Формирование строки даты для одиночного выходного
                var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
                //Формирование строки даты для одиночного выходного, пользовательская(+1)
                var dateStringUser = date.getDate() + "/" + (date.getMonth() + 1) + "/" + date.getFullYear();
                //Формирование строки даты для ежегодного выходного
                var h = peakDates.dates[dateString];
                //Если в массиве выходных есть данная дата
                if (h) {
                    removePeakDate(dateString);
                }
                //Если в массиве нету данной даты
                else {
                    addPeakDate(dateString);
                }
            }
        });
    });
}

//Добавление пикового дня
function addPeakDate(dateString) {
    var token = $("input[name='_token']").val();

    $.ajax({
        url: '/peak/date-add',
        type: 'post',
        dataType: 'json',
        data: {
            _token: token,
            date: dateString
        }
    }).done(function (data) {
        $("#peaks_datepicker").find("td[data-month=" + data.date.month + "][data-year=" + data.date.year + "]").each(function () {
            var day = $(this).find("a").html();
            if (parseInt(day) == parseInt(data.date.day)) {
                $(this).addClass("peak-day");
            }
        });
        runPeakDP();
        updatePeakList();
    });
}

//Удаление пикового дня
function removePeakDate(dateString) {
    var token = $("input[name='_token']").val();

    $.ajax({
        url: '/peak/date-destroy',
        type: 'post',
        dataType: 'json',
        data: {
            _token: token,
            date: dateString
        }
    }).done(function (data) {
        $("#peaks_datepicker").find("td[data-month=" + data.date.month + "][data-year=" + data.date.year + "]").each(function () {
            var day = $(this).find("a").html();
            if (parseInt(day) == parseInt(data.date.day)) {
                $(this).removeClass("peak-day");
            }
        });
        runPeakDP();
        updatePeakList();
    });
}

//Функция обновления списка выходных
function updateHolidaysList() {
    var csrf = $('input[name = "_token"]').val();
    $.ajax({
        url: '/holiday/show',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf
        }
    }).done(function (data) {
        data = $.parseJSON(data);
        $(".holidays-list").html("");
        var html = "";
        //Если массив выходных пуст
        if (data.holidays.length === 0) {
            html += "<div class='holidays_not'>" +
                "<p>You don't have any holidays yet.</p>" +
                "</div>";
        } else {
            //Если массив ывходных не пуст
            for (var i in data.holidays) {
                var desc = data.holidays[i]['desc'];
                //Если описание выходного пустое
                if (desc == "") {
                    desc = "Holiday";
                }
                //Если выходной ежегодный
                if (data.holidays[i]['anual'] == 1) {


                    html += '<div class="following-anual-list list-item" data-anual="1" data-date="' + data.holidays[i]['data'] + '">' +
                        '<div class="list-item-head"><div class="list-item-head-left">' +
                        '<span class="list-item-date">' + data.holidays[i]['date'] + '</span>' +
                        '<span class="list-item-desc">(anual)</span>' +
                        '</div>';
                    if (data.accesses.holiday_destroy == 1) {
                        html += '<div class="list-item-head-right">' +
                            '<span data-anual="1" data-date="' + data.holidays[i]['data'] + '" onclick="deleteHoliday($(this))"><i class="fa fa-trash-o" aria-hidden="true"></i></span>' +
                            '</div>';

                    }

                    html += '<div class="clearfix"></div></div>' +
                        '<div class="list-item-body">' +
                        '<p class="holiday-desc" data-date="' + data.holidays[i]['data'] + '" onclick="';
                    if (data.accesses.holiday_edit == 1) {
                        html += 'showDescInput($(this))';
                    }
                    html += '">' + desc + '</p>';

                    html += '<div class="holiday-desc hidden" data-date="' + data.holidays[i]['data'] + '">' +
                        '<input type="text" class="form-control holiday-desc-input" data-date="' + data.holidays[i]['data'] + '"' +
                        'data-old="' + data.holidays[i]['desc'] + '" value="' + data.holidays[i]['desc'] + '" onblur="changeHoliday($(this))" maxlength="100">' +
                        '</div>';
                    html += '</div></div>';

                } else {
                    //Если выходной разовый
                    //Если выходной уже прошел
                    if (data.holidays[i]['type'] == "past") {

                        html += '<div class="past-holiday-list list-item" data-anual="0" data-date="' + data.holidays[i]['data'] + '">' +
                            '<div class="list-item-head"><div class="list-item-head-left">' +
                            '<span class="list-item-date">' + data.holidays[i]['date'] + '</span>' +
                            '<span class="list-item-desc">(past single)</span>' +
                            '</div>';
                        if (data.accesses.holiday_destroy == 1) {
                            html += '<div class="list-item-head-right">' +
                                '<span data-anual="0" data-date="' + data.holidays[i]['data'] + '" onclick="deleteHoliday($(this))"><i class="fa fa-trash-o" aria-hidden="true"></i></span>' +
                                '</div>';

                        }

                        html += '<div class="clearfix"></div></div>' +
                            '<div class="list-item-body">' +
                            '<p class="holiday-desc" data-date="' + data.holidays[i]['data'] + '" onclick="';
                        if (data.accesses.holiday_edit == 1) {
                            html += 'showDescInput($(this))';
                        }
                        html += '">' + desc + '</p>';

                        html += '<div class="holiday-desc hidden" data-date="' + data.holidays[i]['data'] + '">' +
                            '<input type="text" class="form-control holiday-desc-input" data-date="' + data.holidays[i]['data'] + '"' +
                            'data-old="' + data.holidays[i]['desc'] + '" value="' + data.holidays[i]['desc'] + '" onblur="changeHoliday($(this))" maxlength="100">' +
                            '</div>';
                        html += '</div></div>';

                    } else {
                        //Если выходной еще не прошел


                        html += '<div class="following-holiday-list list-item" data-date="' + data.holidays[i]['data'] + '">' +
                            '<div class="list-item-head"><div class="list-item-head-left">' +
                            '<span class="list-item-date">' + data.holidays[i]['date'] + '</span>' +
                            '<span class="list-item-desc">(single)</span>' +
                            '</div>';
                        if (data.accesses.holiday_destroy == 1) {
                            html += '<div class="list-item-head-right">' +
                                '<span data-anual="0" data-date="' + data.holidays[i]['data'] + '" onclick="deleteHoliday($(this))"><i class="fa fa-trash-o" aria-hidden="true"></i></span>' +
                                '</div>';

                        }

                        html += '<div class="clearfix"></div></div>' +
                            '<div class="list-item-body">' +
                            '<p class="holiday-desc" data-date="' + data.holidays[i]['data'] + '" onclick="';
                        if (data.accesses.holiday_edit == 1) {
                            html += 'showDescInput($(this))';
                        }
                        html += '">' + desc + '</p>';

                        html += '<div class="holiday-desc hidden" data-date="' + data.holidays[i]['data'] + '">' +
                            '<input type="text" class="form-control holiday-desc-input" data-date="' + data.holidays[i]['data'] + '"' +
                            'data-old="' + data.holidays[i]['desc'] + '" value="' + data.holidays[i]['desc'] + '" onblur="changeHoliday($(this))" maxlength="100">' +
                            '</div>';
                        html += '</div></div>';

                    }
                }
            }
        }
        removeLoader();
        $(".holidays-list").append(html);
    });
}

function updatePeakList() {
    var token = $("input[name=_token]").val();

    $.ajax({
        url: '/peak/show',
        type: 'post',
        dataType: 'json',
        data: {
            _token: token
        }
    }).done(function (data) {
        //if (data.success) {
        var html = '';
        for (var i in data.dates) {
            html += '<div class=" peak-date" data-date="' + data.dates[i]['date'] + '" onmouseover="showPeaksButtons($(this))" onmouseout="hidePeaksButtons($(this))">' +
                '<div class="peak-date-left">' +
                '<p class="peak-date-p" data-date="' + data.dates[i]['date'] + '"';
            html += '">' + data.dates[i]['user_date'] + '</p>';
            html += '</div>' +
                '<div class="peak-date-right">';
            if (data.accesses.peak_destroy == 1) {
                html += '<span data-date="' + data.dates[i]['date'] + '" onclick="deletePeakDate($(this))"><i class="fa fa-trash-o" aria-hidden="true"></i></span>';
            }
            html += '</div>' +
                '<div class="clearfix"></div>' +
                '</div>';
        }

        $(".add-peak-date").removeClass("has-error").find(".help-block").addClass("hidden");

        $(".j-peaks-date").html("");
        $(".j-peaks-date").append(html);

        /*} else if (data.errors) {
         for (var i in data.errors) {
         $(".add-peak-date").addClass('has-error').find(".help-block").removeClass("hidden").find("strong")
         .html(data.errors[i]);
         }
         }*/

    });
}

//Показывает поле изменения описания выходного
function showDescInput(el) {
    var date = el.data("date");
    $("p.holiday-desc[data-date=" + date + "]").addClass("hidden");
    $("div.holiday-desc[data-date=" + date + "]").removeClass("hidden");
    $(".holiday-desc-input[data-date=" + date + "]").focus();
}

//Сохраняет измененное описание выходного
function changeHoliday(el) {
    var date = el.data("date");
    $("p.holiday-desc[data-date=" + date + "]").removeClass("hidden");
    $("div.holiday-desc[data-date=" + date + "]").addClass("hidden");
    var old = el.data("old");
    var value = el.val();
    //Если новое описание не совпадает со старым - обновляет описание
    if (old != value) {
        addLoader($("div.holiday-desc[data-date=" + date + "]"));
        var csrf = $('input[name = "_token"]').val();
        $.ajax({
            url: '/holiday/ajax-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                date: date,
                desc: value
            }
        }).done(function (data) {
            if (data == 1) {
                el.data("old", value)
                runHollydayDP();
                updateHolidaysList();
            } else {
                return false;
            }
        });
    }
}

//Удаляет выходной
function deleteHoliday(el) {
    addLoader(el);
    var csrf = $('input[name = "_token"]').val();
    var date = el.data("date");
    var anual = el.data("anual");
    $.ajax({
        url: '/holiday/ajax-destroy',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            date: date,
            anual: anual
        }
    }).done(function (data) {
        data = $.parseJSON(data);
        //Если это ежегодный выходной
        if (data.year != "0001") {
            $("#holidays_datepicker").find("td[data-month=" + data.month + "][data-year=" + data.year + "]").each(function () {
                var day = $(this).find("a").html();
                if (parseInt(day) == parseInt(data.day)) {
                    $(this).removeClass("following-holiday");
                    $(this).removeClass("past-holiday");
                    $(this).removeClass("following-anual");
                    $(this).removeClass("past-anual");
                }
            });
        } else {
            //Если это одноразовый выходной
            $("#holidays_datepicker").find("td[data-month=" + data.month + "]").each(function () {
                var day = $(this).find("a").html();
                if (parseInt(day) == parseInt(data.day)) {
                    $(this).removeClass("following-holiday");
                    $(this).removeClass("past-holiday");
                    $(this).removeClass("following-anual");
                    $(this).removeClass("past-anual");
                }
            });
        }

        $(".old-holiday").css("display", "none");
        runHollydayDP();
        updateHolidaysList();
    });
}


function startServices() {
    var ns = $('ol.services').nestedSortable({
        forcePlaceholderSize: true,
        handle: 'span.move_me',
        helper: 'original',
        items: 'li',
        cursor: 'move',
        opacity: .6,
        placeholder: 'placeholder',
        revert: 80,
        tabSize: 25,
        tolerance: 'pointer',
        toleranceElement: '> div',
        isTree: false,
        expandOnHover: 700,
        startCollapsed: false,
        maxLevels: 1,
        update: function () {
            updateServices();
        }
    });

}

function updateServices() {
    arraied = $('ol.services').nestedSortable('toArray', {startDepthCount: 0});
    var csrf = $("input[name='_token']").val();
    var order = JSON.stringify(arraied);
    $.ajax({
        url: '/addservice/order-edit',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            order: order
        }
    }).done(function (data) {
    });
}

function startPackings() {
    var ns = $('ol.packings').nestedSortable({
        forcePlaceholderSize: true,
        handle: 'span.move_me',
        helper: 'original',
        items: 'li',
        cursor: 'move',
        opacity: .6,
        placeholder: 'placeholder',
        revert: 80,
        tabSize: 25,
        tolerance: 'pointer',
        toleranceElement: '> div',
        isTree: false,
        expandOnHover: 700,
        startCollapsed: false,
        maxLevels: 1,
        update: function () {
            updatePackings();
        }
    });

}

function updatePackings() {
    arraied = $('ol.packings').nestedSortable('toArray', {startDepthCount: 0});
    var csrf = $("input[name='_token']").val();
    var order = JSON.stringify(arraied);
    $.ajax({
        url: '/cpacking/order-edit',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            order: order
        }
    }).done(function (data) {
    });
}

$(document).ready(function () {
//Holidays -------------------------------------------------------------------------------------------------------------
    startServices();
    startPackings();
    //При загрузке страницы запускает функцию для отображения календаря
    runHollydayDP();
    runPeakDP();

    //При клике вне блока добавления выходного прячет блок добавления выходного
    $(document).click(function (event) {
        if ($(event.target).closest(".new-holiday").length)
            return;
        $(".new-holiday").css("display", "none");
        event.stopPropagation();
    });

    //При клике вне блока редактирования выходного прячет блок редактирования выходного
    $(document).click(function (event) {
        if ($(event.target).closest(".old-holiday").length)
            return;
        $(".old-holiday").css("display", "none");
        event.stopPropagation();
    });

    //Прячет окно добавления выходного
    $(".close-new-holiday").click(function () {
        $(".new-holiday").css("display", "none");
    });

    //Прячет окно редактирования выходного
    $(".close-old-holiday").click(function () {
        $(".old-holiday").css("display", "none");
    });

    //Добавляет новый выходной
    $(".save-new-holiday").click(function () {
        var csrf = $('input[name = "_token"]').val();
        var date = $(".new-holiday-date").val();
        var desc = $(".new-holiday-desc").val();
        var anual = $("input[name='frequency']:checked").val();
        $.ajax({
            url: '/holiday/ajax-add',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                date: date,
                desc: desc,
                anual: anual
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если пришла ошибка с пхп
            if (data.error) {
                $(".new-holiday").css("display", "none");
                return false;
            }
            //Создает обьект даты
            var date = new Date(data.year, data.month, data.day);
            //Если выходной ежегодный
            if (anual == 1) {
                var dateString = "0001-" + date.getMonth() + "-" + date.getDate();
            } else {
                //Если выходной одноразовый
                var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
            }

            var now = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).valueOf();

            $("#holidays_datepicker").find("td[data-month=" + data.month + "][data-year=" + data.year + "]").each(function () {
                var day = $(this).find("a").html();
                if (parseInt(day) == parseInt(data.day)) {
                    if (anual == 1) {
                        if (date.valueOf() < today) {
                            $(this).addClass("past-anual");
                        } else {
                            $(this).addClass("following-anual");
                        }
                    } else {
                        if (data.type == "past") {
                            $(this).addClass("past-holiday");
                        } else {
                            $(this).addClass("following-holiday");
                        }
                    }
                }
            });
            $(".new-holiday").css("display", "none");
            runHollydayDP();
            updateHolidaysList();
        });
    });

    //Изменяет описание выходного
    $(".old-holiday-desc").change(function () {
        var csrf = $('input[name = "_token"]').val();
        var date = $(".old-holiday-date").val();
        var desc = $(".old-holiday-desc").val();
        $.ajax({
            url: '/holiday/ajax-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                date: date,
                desc: desc
            }
        }).done(function (data) {
            if (data == 1) {
                runHollydayDP();
                updateHolidaysList();
            } else {
                return false;
            }
        });
    });

    //Удаляет выходной
    $(".del-old-holiday").click(function () {
        var csrf = $('input[name = "_token"]').val();
        var date = $(".old-holiday-date").val();
        var anual = holidayDates.dates[date]['anual'];
        $.ajax({
            url: '/holiday/ajax-destroy',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                date: date,
                anual: anual
            }
        }).done(function (data) {
            data = $.parseJSON(data);

            $("#holidays_datepicker").find("td[data-month=" + data.month + "][data-year=" + data.year + "]").each(function () {
                var day = $(this).find("a").html();
                if (parseInt(day) == parseInt(data.day)) {
                    $(this).removeClass("following-holiday");
                    $(this).removeClass("past-holiday");
                    $(this).removeClass("following-anual");
                    $(this).removeClass("past-anual");
                }
            });
            $(".old-holiday").css("display", "none");
            runHollydayDP();
            updateHolidaysList();
        });
    });

    //Показывает/прячет список выходных
    $(".view-holidays-list").click(function () {

        if (!($('.holidays-list').is(":visible"))) {
            $(".holidays-list").show(200);
            $('.view-holidays_text').html('Close all');
            $('.view-holidays_arrow').html('<i class="fa fa-chevron-up" aria-hidden="true"></i>');
        } else {
            $('.holidays-list').hide(200);
            $('.view-holidays_text').html('Show all');
            $('.view-holidays_arrow').html('<i class="fa fa-chevron-down" aria-hidden="true"></i>');
        }
    });

    //Показывает/прячет список пиковые дни
    $(".view-date-peaks").click(function () {

        if (!($('.j-peaks-date').is(":visible"))) {
            $(".j-peaks-date").show(200);
            $('.view-date-peaks_text').html('Close all');
            $('.view-date-peaks_arrow').html('<i class="fa fa-chevron-up" aria-hidden="true"></i>');
        } else {
            $('.j-peaks-date').hide(200);
            $('.view-date-peaks_text').html('Show all');
            $('.view-date-peaks_arrow').html('<i class="fa fa-chevron-down" aria-hidden="true"></i>');
        }
    });

    //Price per --------------------------------------------------------------------------------------------------------


//Peaks ----------------------------------------------------------------------------------------------------------------
    //При изменении дней недели
    $(".peaks-days").change(function () {
        var peaksDays = $("#peaks_days").serialize();
        peaksDays += '&' + $("#peaks_days_mon").serialize();
        var csrf = $("input[name='_token']").val();

        $.ajax({
            url: '/peak/days-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                peaksdays: peaksDays
            }
        });
    });

    /*//Добавление нового числа пикового дня
     $("#add_peak_date_btn").click(function () {
     var date = $("#add_peak_date_input").val().trim();
     var csrf = $("input[name='_token']").val();
     $.ajax({
     url: '/peak/date-add',
     type: 'POST',
     dataType: 'html',
     data: {
     _token: csrf,
     date: date
     }
     }).done(function (data) {
     data = $.parseJSON(data);
     if (data.success) {


     var html = '<div class=" peak-date" data-id="' + data.success + '" onmouseover="showPeaksButtons($(this))" onmouseout="hidePeaksButtons($(this))">' +
     '<div class="peak-date-left">' +
     '<p class="peak-date-p" data-id="' + data.success + '" onclick="';
     if (data.accesses.peak_edit == 1) {
     html += "showPeakDateInp($(this))";
     }
     html += '">' + date + '</p>';
     if (data.accesses.peak_edit == 1) {
     html += "<input type='text' class='form-control peak-date-input hidden' data-id='" + data.success + "'" +
     "data-old='" + date + "' value='" + date + "' onblur='hidePeakDateInp($(this))'>" +
     "<span class='help-block hidden'>" +
     "<strong></strong>" +
     "</span>";

     }
     html += '</div>' +
     '<div class="peak-date-right">';
     if (data.accesses.peak_destroy == 1) {
     html += '<span data-id="' + data.success + '" onclick="deletePeakDate($(this))"><i class="fa fa-trash-o" aria-hidden="true"></i></span>';
     }
     html += '</div>' +
     '<div class="clearfix"></div>' +
     '</div>';


     $(".add-peak-date").removeClass("has-error").find(".help-block").addClass("hidden");
     $(".peaks-date").append(html);
     $("#add_peak_date_input").val("");
     } else if (data.errors) {
     for (var i in data.errors) {
     $(".add-peak-date").addClass('has-error').find(".help-block").removeClass("hidden").find("strong")
     .html(data.errors[i]);
     }
     }
     });
     });*/
});

//Показывает кнопки сервиса при наведении
function showServiceButtons(el) {
    //el.find(".service-buttons").removeClass("hidden");
    el.find(".service-buttons").show();
}

//Прячет кнопки сервиса при выводе мышки за область сервиса
function hideServiceButtons(el) {
    //el.find(".service-buttons").addClass("hidden");
    el.find(".service-buttons").hide();
}

//При клике на значения сервиса - показывает инпут для его изменения
function showServiceInput(el) {
    var id = el.data('id');
    var type = el.data('type');
    $(el).addClass("hidden");
    $("input[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden").focus();
}

//При потере фокуса изменения информации - отправляет аякс на изменение
function changeService(el) {
    var old = toString(el.data('old')).trim();
    var value = el.val().trim();
    var type = el.data('type').trim();
    var id = el.data('id');
    //Если старое значение не совпадает с новым
    if (old != value) {
        var csrf = $('input[name = "_token"]').val();

        //Валидация
        if (type == "service_name") {
            if (value.length < 1) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Please, enter the item name.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else if (value.length > 50) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Name of item can't be longer then 50 symbols.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else if (!namePattern.test(value)) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Incorrect name of item.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else {
                $("div[data-type=" + type + "][data-id=" + id + "]").removeClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").addClass("hidden");
            }
        } else if (type == "service_price") {
            if (value.length > 0) {
                if (!floatPattern.test(value)) {
                    $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                    $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Incorrect value of item weight.");
                    $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                    return false;
                } else {
                    $("div[data-type=" + type + "][data-id=" + id + "]").removeClass("has-error");
                    $("span[data-type=" + type + "][data-id=" + id + "]").addClass("hidden");
                }
            } else {
                value = "0.00";
            }
        }
        addLoader(el);
        //Если валидация прошла успешно - отправляет аякс
        $.ajax({
            url: '/addservice/ajax-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                value: value,
                type: type,
                id: id
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если изменение прошло успешно
            if (data.success) {
                el.data('old', value);
                el.val(value);
                $("p[data-type=" + type + "][data-id=" + id + "]").html(value);
                removeLoader();
            } else if (data.error) {
                //Если валидация пхп вернула ошибку
                $("p[data-id=" + id + "][data-type=" + type + "]").addClass("hidden");
                $("input[data-id=" + id + "][data-type=" + type + "]").removeClass('hidden');
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html(data.error);
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                removeLoader();
                return false;
            }
        });
    }
    $(el).addClass("hidden");
    $("p[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
}

//Удаляет сервис
function deleteService(el) {
    var csrf = $('input[name = "_token"]').val();
    var id = el.data('id');
    addLoader(el);
    $.ajax({
        url: '/addservice/ajax-destroy',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            id: id
        }
    }).done(function (data) {
        $(".service[data-id=" + id + "]").remove();
        removeLoader();
    });
}

//Показывает кнопки покования при наведении
function showPackingButtons(el) {
    el.find(".packing-buttons").removeClass("hidden-md hidden-lg hidden-sm");
}

//Прячет кнопки покования при выводе мышки за область сервиса
function hidePackingButtons(el) {
    el.find(".packing-buttons").addClass("hidden-md hidden-lg hidden-sm");
}

//При клике на значения покования - показывает инпут для его изменения
function showPackingInput(el) {
    var id = el.data('id');
    var type = el.data('type');
    $(el).addClass("hidden");
    $("input[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden").focus();
}

//Изменение значения покавания
function changePacking(el) {
    var id = el.data("id");
    var type = el.data("type");
    var old = el.data("old");
    var value = el.val().trim();

    if (value != old) {
        var csrf = $('input[name = "_token"]').val();

        //Валидация
        if (type == "c_packing_name") {
            if (value.length < 1) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Please, enter the packing name.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else if (value.length > 50) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Name of packing can't be longer then 50 symbols.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else if (!namePattern.test(value)) {
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Incorrect name of packing.");
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                return false;
            } else {
                $("div[data-type=" + type + "][data-id=" + id + "]").removeClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").addClass("hidden");
            }
        } else {
            if (value.length > 0) {
                if (!floatPattern.test(value)) {
                    $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                    $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html("Incorrect value of price.");
                    $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                    return false;
                } else {
                    $("div[data-type=" + type + "][data-id=" + id + "]").removeClass("has-error");
                    $("span[data-type=" + type + "][data-id=" + id + "]").addClass("hidden");
                }
            } else {
                value = "0.00";
            }
        }
        addLoader(el);
        //Если валидация прошла успешно - отправляет аякс
        $.ajax({
            url: '/cpacking/ajax-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                value: value,
                type: type,
                id: id
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если изменение прошло успешно
            if (data.success) {
                el.data('old', value);
                el.val(value);
                $("p[data-type=" + type + "][data-id=" + id + "]").html(value);
                removeLoader();
            } else if (data.error) {
                //Если валидация пхп вернула ошибку
                $("p[data-id=" + id + "][data-type=" + type + "]").addClass("hidden");
                $("input[data-id=" + id + "][data-type=" + type + "]").removeClass('hidden');
                $("div[data-type=" + type + "][data-id=" + id + "]").addClass("has-error");
                $("span[data-type=" + type + "][data-id=" + id + "]").find("strong").html(data.error);
                $("span[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
                removeLoader();
                return false;
            }
        });
    }
    //Прячет инпут и показывает строку
    $(el).addClass("hidden");
    $("p[data-type=" + type + "][data-id=" + id + "]").removeClass("hidden");
}

//Удаляет пакование
function deletePacking(el) {
    var id = el.data("id");
    var csrf = $('input[name = "_token"]').val();
    addLoader(el);
    $.ajax({
        url: '/cpacking/ajax-destroy',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            id: id
        }
    }).done(function (data) {
        $(".packing[data-id=" + id + "]").remove();
        removeLoader();
    });
}

//Peaks ----------------------------------------------------------------------------------------------------------------
/*//Показывает инпут для изменения числа пикового дня
 function showPeakDateInp(el) {
 var id = el.data("id");
 el.addClass("hidden");
 $(".peak-date-input[data-id='" + id + "']").removeClass("hidden").focus();
 }*/

/*//Изменяет число пикового дня
 function hidePeakDateInp(el) {
 var id = el.data("id");
 var old = el.data("old");
 var value = el.val().trim();
 //Если старое значение не совпадает с новым
 if (value != old) {
 var csrf = $("input[name='_token']").val();
 $.ajax({
 url: '/peak/date-edit',
 type: 'POST',
 dataType: 'html',
 data: {
 _token: csrf,
 id: id,
 date: value
 }
 }).done(function (data) {
 data = $.parseJSON(data);
 if (data.success) {
 $(".peak-date[data-id='" + id + "']").removeClass("hidden").find(".help-block").addClass("hidden");
 el.data("old", value);
 el.addClass("hidden");
 $(".peak-date-p[data-id='" + id + "']").html(value);
 $(".peak-date-p[data-id='" + id + "']").removeClass("hidden");
 } else if (data.errors) {
 for (var i in data.errors) {
 $(".peak-date[data-id='" + id + "']").addClass("has-error").find(".help-block").removeClass("hidden")
 .find("strong").html(data.errors[i]);
 }
 }
 });
 } else {
 el.addClass("hidden");
 $(".peak-date-p[data-id='" + id + "']").removeClass("hidden");
 }
 }*/

//Удаляет число пикового дня
function deletePeakDate(el) {
    var csrf = $("input[name='_token']").val();
    var date = el.data('date');
    $.ajax({
        url: '/peak/date-destroy',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: csrf,
            date: date
        }
    }).done(function (data) {
        console.log(data);
        el.closest(".peak-date").remove();
        $("#peaks_datepicker").find("td[data-month=" + data.date.month + "][data-year=" + data.date.year + "]").each(function () {
            var day = $(this).find("a").html();
            if (parseInt(day) == parseInt(data.date.day)) {
                $(this).removeClass("peak-day");
            }
        });
        runPeakDP();
    });
}

//Показывает кнопки пиковых дней
function showPeaksButtons(el) {
    el.find(".peaks-buttons").removeClass("hidden");
}

//Прячет кнопки пиковых дней
function hidePeaksButtons(el) {
    el.find(".peaks-buttons").addClass("hidden");
}

//Добавляет к блоку гифку загрузки
function addLoader(el) {
    el.parent("div").css("position", "relative");
    el.parent("div").append("<div class='loader'></div>");
}

//Удаляет гифку загрузки с блока
function removeLoader() {
    $(".loader").removeClass("loader");
}

//Taxes ----------------------------------------------------------------------------------------------------------------
//Изменение налога
function editTax(el) {
    var csrf = $("input[name='_token']").val();
    var percent = el.val();
    var tax = el.data('tax');
    var old = el.data('old');

    if (percent.trim() == old) {
        return false;
    }

    if (percent.length > 0) {
        if (!floatPattern.test(percent)) {
            el.parent("div").addClass("has-error");
            el.parent("div").find("strong").html("Incorrect value of percent.");
            el.parent("div").find("span").removeClass("hidden");
            return false;
        } else if (parseFloat(percent) > 100) {
            el.parent("div").addClass("has-error");
            el.parent("div").find("strong").html("Percent value can't be greater than 100.");
            el.parent("div").find("span").removeClass("hidden");
            return false;
        } else if (parseFloat(percent) < 0) {
            el.parent("div").addClass("has-error");
            el.parent("div").find("strong").html("Percent value can't be less than 0.");
            el.parent("div").find("span").removeClass("hidden");
            return false;
        } else {
            el.parent("div").removeClass("has-error");
            el.parent("div").find("span").addClass("hidden");
        }
    } else {
        el.parent("div").addClass("has-error");
        el.parent("div").find("strong").html("This field is required.");
        el.parent("div").find("span").removeClass("hidden");
        return false;
    }

    $.ajax({
        url: '/tax/ajax-edit',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: csrf,
            percent: percent,
            tax: tax
        }
    }).done(function (data) {
        if (data.success == 1) {
            el.data('old', percent);
        } else {

        }
    });
}

/*Загружает все Notification*/
function getAllNotification(page, id, offset, limit) {
    var csrf = $("input[name='_token']").val();

    $.ajax({
        url: '/notification/ajax-index',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: csrf,
            page: page,
            id: id,
            offset: offset,
            limit: limit,
        },
        beforeSend: function () {
            console.log("load");
        },
    })
        .done(function (data) {
            data.forEach(function (item, i, arr) {
                var html = '<div class="row ' + item.notification_status + '">' +
                    '<div class="col-md-10">' +
                    '<p>' + item.notification_text + '</p>' +
                    '</div>' +
                    '<div class="col-md-2 date_time">' +
                    '<span class="date">' + item.date + '</span>' +
                    '<p class="time">at ' + item.time + '</p>' +
                    '</div>' +
                    '</div>';
                $(".notification_all").append(html);
            });

            $('.btn-getallnot').hide();
            $('.footer-getallnot').hide();

        })
        .error(function (data) {

        });
}