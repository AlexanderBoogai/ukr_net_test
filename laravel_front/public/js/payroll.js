//Payroll --------------------------------------------------------------------------------------------------------------
//Оплата пейролла
function payPayroll(el) {
    var csrf = $("input[name='_token']").val();
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var sum = $(".pay-sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").val().trim();
    var pocket = $(".pay-pocket[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").val();

    $.ajax({
        url: "/payroll/payroll-pay",
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            id: id,
            order: order,
            worker: worker,
            sum: sum,
            pocket: pocket
        }
    }).done(function (data) {
        data = $.parseJSON(data);
        //Если оплата прошла успешно
        if (data.success) {
            $(".balance-due[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                .html(data.success.balance_due);
            $(".paid[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                .html(data.success.paid);
            if (data.success.status == 1) {
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find(".not-paid").remove();
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find(".payed").remove();
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .prepend("<span class='payed'></span>");
            } else {
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find(".payed").remove();
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find(".not-paid").remove();
                var html = "<p class='not-paid'>$ <span class='need-pay' data-id='" + id + "' data-order='" + order + "' data-worker='" + worker + "'" +
                    "onclick='";
                if (data.accesses.payroll_pay == 1) {
                    html += "showPayWindow($(this))";
                }
                html += "'>" +
                    "" + data.success.need_pay + "" +
                    "</span>" +
                    "</p>"
                $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .prepend(html);
                $(".need-pay[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .html(data.success.need_pay);
            }
            $(".pay-window[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                .find(".has-error").removeClass("has-error").find(".help-block").addClass("hidden");
            updateCalculation();
            $(".pay-window[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").addClass("hidden");
            updateTransactions(id);
            //Если данные не прошли валидацию - выводит ошибки
        } else if (data.errors) {
            for (var i in data.errors) {
                $(".pay-window[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find(".pay-" + i + "-div").addClass("has-error").find(".help-block").removeClass("hidden")
                    .find("strong").html(data.errors[i]);
            }
        }
    });
}

//Показывает инпут для изменения значений в пейролле
function showPayrollInp(el) {
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var type = el.data("type");
    el.addClass("hidden");


    $("." + type + "-inp[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").parent('.after_border')
        .removeClass("hidden");

    $("." + type + "-inp[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
        .removeClass("hidden").focus();


}

//Прячет инпут для изменения значений в пейролле и применяет изменения
function hidePayrollInp(el) {
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var type = el.data("type");
    var old = el.data("old");
    var value = el.val();

    //Если старое значение не совпадает с новым - выполняет изменение
    if (value.trim() != old) {
        var csrf = $("input[name='_token']").val();
        $.ajax({
            url: '/payroll/payroll-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                id: id,
                order: order,
                worker: worker,
                type: type,
                value: value
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если изменение прошло успешно
            if (data.success) {
                $(".balance-due[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .html(data.success.balance_due);
                $(".paid[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .html(data.success.paid);
                if (data.success.status == 1) {
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".not-paid").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".payed").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .prepend("<span class='paid'></span>");
                } else {
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".payed").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".not-paid").remove();
                    var html = "<p class='not-paid'>$ <span class='need-pay' data-id='" + id + "' data-order='" + order + "' data-worker='" + worker + "'" +
                        "onclick='";
                    if (data.accesses.payroll_pay == 1) {
                        html += "showPayWindow($(this))";
                    }
                    html += "'>" +
                        "" + data.success.need_pay + "" +
                        "</span>" +
                        "</p>"
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .prepend(html);
                    $(".need-pay[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .html(data.success.need_pay);
                }
                $("." + type + "-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .find("." + type).html(value);
                $("." + type + "-inp[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .val(value);
                el.data("old", value);
                $("." + type + "-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .removeClass("has-error").find(".help-block").addClass("hidden");
                el.addClass("hidden");
                el.parent('.after_border').addClass("hidden");
                if ($("span").is(".fica_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".fica_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.fica_sum);
                }
                if ($("span").is(".state_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".state_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.state_tax_sum);
                }
                if ($("span").is(".federal_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".federal_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.federal_tax_sum);
                }
                $(".payment_tax[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.payment_tax);
                updateCalculation();
                $("." + type + "-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .removeClass("hidden");

                //Если данные не прошли валидацию
            } else if (data.errors) {
                for (var i in data.errors) {
                    $("." + type + "-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .addClass("has-error").find(".help-block").removeClass("hidden").find("strong").html(data.errors[i]);
                }
            }
        });

        //Если старое и новое значения совпадают
    } else {
        el.addClass("hidden");

        el.parent('.after_border').addClass("hidden");


        $("." + type + "-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
            .removeClass("has-error").find(".help-block").addClass("hidden");
        $("." + type + "-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
            .removeClass("hidden");
    }
}

function showServiceInp(el) {
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var service = el.data("service");
    el.addClass("hidden");

    $(".after_border[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='" + service + "']")
        .removeClass('hidden').find('input').focus();
}

function hideServiceInp(el) {
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var service = el.data("service");
    var old = el.data("old");
    var value = el.val();

    //Если старое значение не совпадает с новым - выполняет изменение
    if (value.trim() != old) {
        var csrf = $("input[name='_token']").val();
        $.ajax({
            url: '/payroll/payroll-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                id: id,
                order: order,
                worker: worker,
                type: 'service',
                service: service,
                value: value
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если изменение прошло успешно
            if (data.success) {
                $(".balance-due[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .html(data.success.balance_due);
                $(".paid[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                    .html(data.success.paid);
                if (data.success.status == 1) {
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".not-paid").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".payed").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .prepend("<span class='paid'></span>");
                } else {
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".payed").remove();
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .find(".not-paid").remove();
                    var html = "<p class='not-paid'>$ <span class='need-pay' data-id='" + id + "' data-order='" + order + "' data-worker='" + worker + "'" +
                        "onclick='";
                    if (data.accesses.payroll_pay == 1) {
                        html += "showPayWindow($(this))";
                    }
                    html += "'>" +
                        "" + data.success.need_pay + "" +
                        "</span>" +
                        "</p>"
                    $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .prepend(html);
                    $(".need-pay[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                        .html(data.success.need_pay);
                }
                $(".payroll-service-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
                    .html(value);
                el.data("old", value);
                $(".payroll-service[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
                    .removeClass("has-error").find(".help-block").addClass("hidden");

                $(".after_border[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='" + service + "']")
                    .addClass("hidden");

                if ($("span").is(".fica_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".fica_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.fica_sum);
                }
                if ($("span").is(".state_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".state_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.state_tax_sum);
                }
                if ($("span").is(".federal_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")) {
                    $(".federal_tax_sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.federal_tax_sum);
                }
                $(".payment_tax[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").html(data.success.payment_tax);
                updateCalculation();
                $(".payroll-service-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
                    .removeClass("hidden");

                //Если данные не прошли валидацию
            } else if (data.errors) {
                for (var i in data.errors) {
                    $(".payroll-service[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
                        .addClass("has-error").find(".help-block").removeClass("hidden").find("strong").html(data.errors[i]);
                }
            }
        });

        //Если старое и новое значения совпадают
    } else {
        //el.addClass("hidden");

        el.parent('.after_border').addClass("hidden");


        $(".payroll-service[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
            .removeClass("has-error").find(".help-block").addClass("hidden");
        $(".payroll-service-p[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "'][data-service='"+service+"']")
            .removeClass("hidden");
    }
}

//Обновляет общий подсчет заплаченого и сколько нужно заплатить
function updateCalculation() {
    var csrf = $("input[name='_token']").val();
    var worker = $("#current_worker").val();
    var order = $("#current_order").val();
    var type = $("#page_type").val();
    $.ajax({
        url: '/payroll/calculation-edit',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            worker: worker,
            order: order,
            type: type
        }
    }).done(function (data) {
        data = $.parseJSON(data);
        if (data.paid != null) {
            $(".paid-all").html(data.paid);
        } else {
            $(".paid-all").html(0);
        }
        if (data.need_to_pay != null) {
            $(".need-to-pay-all").html(data.need_to_pay);
        } else {
            $(".need-to-pay-all").html(0);
        }
    });
}

//Показывает окно для оплаты пейролла
function showPayWindow(el) {
    var id = el.data("id");
    var order = el.data("order");
    var worker = el.data("worker");
    var sum = el.html().trim();
    $(".pay-sum[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").val(sum);
    $(".pay-window[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']").removeClass("hidden");
    $('.bg_pay-window').fadeIn(200);
}


//Показывает транзакции пейролла
function showTransactions(el) {
    el.find(".transactions-window").removeClass("hidden");
}

//Скрывает транзакции пейролла
function hideTransactions(el) {
    el.find(".transactions-window").addClass("hidden");
}

//Обновляет транзакции пейролла
function updateTransactions(id) {
    var csrf = $("input[name='_token']").val();
    $.ajax({
        url: '/payroll/transactions-edit',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            id: id
        }
    }).done(function (data) {
        data = $.parseJSON(data);
        var html = "";
        for (var i in data) {
            html += "<div class='payroll-transaction'>" +
                "<p class='" + data[i].transaction_type + "'>" +
                "$ " + data[i].transaction_sum + " " + data[i].pocket_type_name + " " + data[i].name + " " + data[i].l_name + " " + data[i].created_at + "</p>" +
                "</div>";
        }
        $(".transactions-window[data-id='" + id + "']").html(html);
    });
}

//Подсчитывает сумму оплаты выбранных пейроллов
function calculateChecked() {
    var csrf = $("input[name='_token']").val();
    var arr = new Array();
    $(".payroll-check").each(function () {
        if ($(this).is(":checked")) {
            var id = $(this).data("id");
            arr.push(id);
        }
    });
    arr = JSON.stringify(arr);

    $.ajax({
        url: '/payroll/checked-index',
        type: 'POST',
        dataType: 'html',
        data: {
            _token: csrf,
            arr: arr
        }
    }).done(function (data) {
        if (data != 0) {
            $(".pay-checked").html(data);
            $(".pay-checked-div").removeClass("hidden");
            $("#pay_checked_modal_btn").removeAttr("disabled");
        } else {
            $(".pay-checked-div").addClass("hidden");
            $("#pay_checked_modal_btn").prop("disabled", true);
        }
    });
}

//Показывает модалку для оплаты текущему работнику
function showPayWorkerModal() {
    updateCalculation();
    if ($(".need-to-pay-all").html().trim() != 0) {
        $("#payWorkerModal").modal("show");
    }
}

//При нажатии на имя/фамилию заказчика из живого поиска - отправдяет форму поиска
function searchCustomerSelect(el) {
    $("#customer").val(el.find("p.customer-list").html());
    $("input[name='customer']").val("");
    $("input[name='name']").val(el.find("p.customer-list").data("name"));
    $("input[name='sname']").val(el.find("p.customer-list").data("sname"));
    checkFilterFormAndSend();
}

/*При нажатии на статус заказа - изменяет значение поля статус и отправляет форму*/
function changeSearchStatus(el) {
    $("input[name='status']").val(el.data('id'));
    checkFilterFormAndSend();
}

//Добавляет к блоку гифку загрузки
function addLoader(el) {
    el.parent("div").css("position", "relative");
    el.parent("div").append("<div class='loader'></div>");
}

//Удаляет гифку загрузки с блока
function removeLoader(el) {
    $(".loader").removeClass("loader");
    //el.parent("div").find(".loader").remove();
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
                var html = "";
                html += "<div class='row success'><div class='col-md-8'>" + item.notification_text + "</div><div class='col-md-4 date_time'><span class='date'>" + item.date + "</span><p class='time'>" + item.time + "</p></div></div>";
                $(".notification_all").append(html);
            });

            $('.btn-getallnot').hide();
            $('.footer-getallnot').hide();

        })
        .error(function (data) {

        });
}

$(document).ready(function () {
    var _today = new Date();
    var timezone_diff = $("#company_timezone_diff_in_minutes").val();
    var user_date = new Date(
        new Date((_today.getTime() + (_today.getTimezoneOffset() * 60000)) + (timezone_diff * 60000))
    );
    var user_dateString = user_date.getFullYear() + "-" + user_date.getMonth() + "-" + user_date.getDate();

    $("#from_date").datepicker({
        dateFormat: "m/d/yy",
        defaultDate: user_date,
        beforeShowDay: function (date) {
            var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
            var classString = "";
            if(dateString == user_dateString){
                classString += " company-today";
                return [true, classString, ''];
            }else{
                return [true, '', ''];
            }
        }
    });
    $("#till_date").datepicker({
        dateFormat: "m/d/yy",
        defaultDate: user_date,
        beforeShowDay: function (date) {
            var dateString = date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate();
            var classString = "";
            if(dateString == user_dateString){
                classString += " company-today";
                return [true, classString, ''];
            }else{
                return [true, '', ''];
            }
        }
    });

    //Показывает детальную информацию о заказе
    $(".order_id_hov").mouseover(function () {
        var id = $(this).data('id');
        $(".orders-all-info.mob_" + id).show();
    });

    //Прячет детальную информацию о заказе
    $(".order_id_hov").mouseout(function () {
        var id = $(this).data('id');
        $(".orders-all-info.mob_" + id).hide();
    });


    //Показывает детальную информацию о заказе
    $(".additional_info").mouseover(function () {
        var id = $(this).data('id');
        $(".order-info.pay.mob_" + id).show();
    });
    //Прячет детальную информацию о заказе
    $(".additional_info").mouseout(function () {
        var id = $(this).data('id');
        $(".order-info.pay.mob_" + id).hide();
    });

    //Показывает детальную информацию о заказе
    $(".order").mouseover(function () {
        $(this).next(".order-info").removeClass("hidden");
    });
    //Прячет детальную информацию о заказе
    $(".order").mouseout(function () {
        $(this).next(".order-info").addClass("hidden");
    });

    /*Показывает статусы для изминения*/
    $(".status_list").click(function () {
        var order = $(this).data("order");
        $(".order-statuses").addClass("hidden");
        $(".order-statuses[data-order='" + order + "']").removeClass("hidden");
        return false;
    });

    $(document).click(function (event) {
        if ($(event.target).closest(".order-statuses").length) {
            return;
        } else if ($(event.target).closest(".status").length) {
            return;
        }
        $(".order-statuses").addClass("hidden");
        event.stopPropagation();
    });

    /*Изменяет статус заказа в */
    $(".status").click(function () {
        addLoader($(".container"));
        var order = $(this).data('order');
        var status = $(this).data('id');
        var name = $(this).data("name");
        var color = $(this).data("color");
        var csrf = $("input[name='_token']").val();

        $.ajax({
            url: '/order/status-edit',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                order: order,
                status: status
            }
        }).done(function (data) {
            console.log(data);
            if (data == 1) {
                $(".status_list[data-order='" + order + "']").css("background-color", color).html(name);
                removeLoader($(".container"));
                $(".order-statuses[data-order='" + order + "']").addClass("hidden");
            }
        });
        return false;
    });

    //При клике вне блока оплаты прячет блок доплаты
    $(document).click(function (event) {
        if ($(event.target).closest(".need-pay-div").length) {
            if ($(event.target).closest(".need-pay-div").find(".not-paid").html()) {
                $(".pay-window").addClass("hidden");

                $(event.target).closest(".need-pay-div").find(".pay-window").removeClass("hidden");
                return;
            }
        } else {
            $(".pay-window").addClass("hidden");
            $(".bg_pay-window").fadeOut(200);
        }
        event.stopPropagation();
    });

    //Оплата выбранных пейроллов
    $("#pay_checked").click(function () {
        var csrf = $("input[name='_token']").val();
        var pocket = $("#pay_checked_pocket").val();
        var arr = new Array();
        $(".payroll-check").each(function () {
            if ($(this).is(":checked")) {
                var id = $(this).data("id");
                arr.push(id);
            }
        });
        arr = JSON.stringify(arr);

        $.ajax({
            url: '/payroll/checked-pay',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                arr: arr,
                pocket: pocket
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если оплата прошла успешно
            if (data.success) {
                for (var i in data.success) {
                    $(".balance-due[data-id='" + data.success[i].id + "']")
                        .html(data.success[i].balance_due);
                    $(".paid[data-id='" + data.success[i].id + "']")
                        .html(data.success[i].paid);
                    if (data.success[i].status == 1) {
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".not-paid").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".payed").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .prepend("<span class='payed'></span>");
                    } else {
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".payed").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".not-paid").remove();
                        var html = "<p class='not-paid'>$ <span class='need-pay' data-id='" + id + "' data-order='" + order + "' data-worker='" + worker + "'" +
                            "onclick='";
                        if (data.accesses.payroll_pay == 1) {
                            html += "showPayWindow($(this))";
                        }
                        html += "'>" +
                            "" + data.success.need_pay + "" +
                            "</span>" +
                            "</p>"
                        $(".need-pay-div[data-id='" + id + "'][data-order='" + order + "'][data-worker='" + worker + "']")
                            .prepend(html);
                        $(".need-pay[data-id='" + data.success[i].id + "']")
                            .html(data.success[i].need_pay);
                    }
                    var html = "";
                    for (var j in data.success[i].transactions) {
                        html += "<div class='payroll-transaction'>" +
                            "<p class='" + data.success[i].transactions[j].transaction_type + "'>" +
                            "$ " + data.success[i].transactions[j].transaction_sum + " " + data.success[i].transactions[j].pocket_type_name + "" +
                            " " + data.success[i].transactions[j].name + " " + data.success[i].transactions[j].l_name + "" +
                            " " + data.success[i].transactions[j].created_at + "</p>" +
                            "</div>";
                    }
                    $(".transactions-window[data-id='" + data.success[i].id + "']").html(html);
                }
                $(".pay-checked-pocket").removeClass("has-error").find(".help-block").addClass("hidden");

                $(".payroll-check").each(function () {
                    if ($(this).is(":checked")) {
                        $(this).prop("checked", false);
                    }
                });

                $(".payroll-check-all").prop("checked", false);
                updateCalculation();
                calculateChecked();
                $("#payModal").modal("hide");
                //Если данные не прошли валидацию
            } else if (data.errors) {
                for (var i in data.errors) {
                    $(".pay-checked-pocket").addClass("has-error").find(".help-block").removeClass("hidden")
                        .find("strong").html(data.errors[i]);
                }
            }
        });
    });

    //Выбирает все пейроллы заказа
    $(".payroll-check-all").change(function () {
        if ($(this).is(":checked")) {
            $(this).closest(".order_payroll_block").find(".payroll-check").prop("checked", true);
            calculateChecked();
        } else {
            $(this).closest(".order_payroll_block").find(".payroll-check").prop("checked", false);
            calculateChecked();
        }
    });

    //Оплата текущему работнику
    $("#pay_worker").click(function () {
        var csrf = $("input[name='_token']").val();
        var worker = $("#current_worker").val();
        var pocket = $("#pay_worker_pocket").val();

        $.ajax({
            url: '/payroll/worker-pay',
            type: 'POST',
            dataType: 'html',
            data: {
                _token: csrf,
                worker: worker,
                pocket: pocket
            }
        }).done(function (data) {
            data = $.parseJSON(data);
            //Если оплата пройшла успешно
            if (data.success) {
                for (var i in data.success) {
                    $(".balance-due[data-id='" + data.success[i].id + "']")
                        .html(data.success[i].balance_due);
                    $(".paid[data-id='" + data.success[i].id + "']")
                        .html(data.success[i].paid);
                    if (data.success[i].status == 1) {
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".not-paid").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".payed").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .prepend("<span class='payed'></span>");
                    } else {
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".payed").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .find(".not-paid").remove();
                        $(".need-pay-div[data-id='" + data.success[i].id + "']")
                            .prepend(
                                "<p class='not-paid'>$ <span class='need-pay' data-id='" + data.success[i].id + "'" +
                                "onclick='showPayWindow($(this))'>" +
                                "" + data.success[i].need_pay + "" +
                                "</span>" +
                                "</p>");
                        $(".need-pay[data-id='" + data.success[i].id + "']")
                            .html(data.success[i].need_pay);
                    }

                    var html = "";
                    for (var j in data.success[i].transactions) {
                        html += "<div class='payroll-transaction'>" +
                            "<p class='" + data.success[i].transactions[j].transaction_type + "'>" +
                            "$ " + data.success[i].transactions[j].transaction_sum + " " + data.success[i].transactions[j].pocket_type_name + "" +
                            " " + data.success[i].transactions[j].name + " " + data.success[i].transactions[j].l_name + "" +
                            " " + data.success[i].transactions[j].created_at + "</p>" +
                            "</div>";
                    }
                    $(".transactions-window[data-id='" + data.success[i].id + "']").html(html);
                }
                $(".pay-worker-pocket").removeClass("has-error").find(".help-block").addClass("hidden");
                updateCalculation();
                $("#payWorkerModal").modal("hide");
                //Если данные не прошли валидацию
            } else if (data.errors) {
                for (var i in data.errors) {
                    $(".pay-worker-pocket").addClass("has-error").find(".help-block").removeClass("hidden")
                        .find("strong").html(data.errors[i]);
                }
            }
        });
    });

    //При изменении полей формы фильтрации - отправляет форму
    $("#order_id, #from_date, #till_date, #admin, #worker, #not_payed").change(function () {
        checkFilterFormAndSend();
    });

    //При нажатии клавиши Enter на поле поиска по клиенту - отправляет форму
    $('#customer').keypress(function (event) {
        if (event.keyCode == 13) {
            if ($("#customer").val().trim() != $("#customer").data("old").trim()) {
                $("input[name='customer']").val($("#customer").val().trim());
                $("input[name='name']").val("");
                $("input[name='sname']").val("");
                checkFilterFormAndSend();
            }
        }
    });

    //Живой поиск по имени и фамилии заказчика
    $("#customer").bind("keyup", function () {
        var search = $("#customer").val().trim().toLowerCase();
        if (search.length != 0 && search.length > 1) {
            var csrf = $("input[name='_token']").val();
            $.ajax({
                url: '/payroll/live-index',
                type: 'POST',
                dataType: 'html',
                data: {
                    _token: csrf,
                    search: search
                }
            }).done(function (data) {
                console.log($.parseJSON(data));
                if (data) {
                    var html = '';
                    data = $.parseJSON(data);
                    for (var i in data) {
                        html += "<div onclick='searchCustomerSelect($(this))'><span>" + data[i]['id'] + "</span><p class='customer-list' data-name=" +
                            "'" + data[i]['f_name'] + "' data-sname='" + data[i]['l_name'] + "'>" + data[i]['f_name'] +
                            " " + data[i]['l_name'] + "</p></div>";
                    }

                    $(".customers-list").html(html);
                    $(".customers-list").show();
                }
            });
        } else {
            $(".customers-list").html("");
            $(".customers-list").hide();
        }
    });

    //прячет блок живого поиска
    $(document).click(function (event) {
        if ($(event.target).closest(".customers-list").length)
            return;
        if ($("input").is("#customer")) {
            if ($("#customer").val().trim() != $("#customer").data('old')) {
                $("input[name='customer']").val($("#customer").val().trim());
                $("input[name='name']").val("");
                $("input[name='sname']").val("");
                checkFilterFormAndSend();
            } else {
                $(".customers-list").html("");
                $(".customers-list").hide();
            }
        }
    });

    $("#sort_lead, #sort_date, #sort_customer, #sort_phone, #sort_move-type, #sort_move-from, #sort_move-to, #sort_hours, #sort_status").click(function () {
        var sort = $(this).data('sort');
        var old_sort = $("input[name='sort']").val();
        var order = 'ASC';
        if (sort == old_sort) {
            var old_order = $("input[name='order']").val();
            if (order == old_order) {
                var order = 'DESC';
            }
        }
        $("input[name='sort']").val(sort);
        $("input[name='order']").val(order);
        checkFilterFormAndSend();
    });
});

function checkFilterFormAndSend() {

    if ($("select").is("#worker")) {
        var worker = $("#worker").val().trim();
        if (worker != "") {
            $("input[name=worker]").val(worker);
        } else {
            $("input[name=worker]").remove();
        }
    }

    if ($("input").is("#order_id")) {
        var order_id = $("#order_id").val().trim();
        if (order_id.length == 0) {
            $("input[name='order_id']").remove();
        } else {
            $("input[name='order_id']").val(order_id);
        }
    }

    var from = $("#from_date").val().trim();
    if (from.length == 0) {
        $("input[name='from']").remove();
    } else {
        $("input[name='from']").val(from);
    }


    var till = $("#till_date").val().trim();
    if (till.length == 0) {
        $("input[name='till']").remove();
    } else {
        $("input[name='till']").val(till);
    }

    if ($("input[name='customer']").val().trim().length == 0) {
        $("input[name='customer']").remove();
    }
    if ($("input[name='name']").val().trim().length == 0) {
        $("input[name='name']").remove();
    }
    if ($("input[name='sname']").val().trim().length == 0) {
        $("input[name='sname']").remove();
    }
    if ($("input[name='phone']").val().trim().length == 0) {
        $("input[name='phone']").remove();
    }
    if ($("input[name='email']").val().trim().length == 0) {
        $("input[name='email']").remove();
    }

    if ($("select").is("#admin")) {
        var admin = $("#admin").val();
        if (admin == 0) {
            $("input[name='admin']").remove();
        } else {
            $("input[name='admin']").val(admin);
        }
    }

    if ($("input").is("input[name=sort]")) {
        if ($("input[name='sort']").val().trim().length == 0) {
            $("input[name='sort']").remove();
            $("input[name='order']").remove();
        }
    }

    if ($("input").is("#not_payed")) {
        if ($("#not_payed").is(":checked")) {
            $("input[name=not_payed]").val(1);
        } else {
            $("input[name=not_payed]").remove();
        }
    }

    $("#filter_form").submit();
    event.stopPropagation();
}