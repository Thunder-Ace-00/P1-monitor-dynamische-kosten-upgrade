<?php
include_once '/p1mon/www/util/page_header.php';
include_once '/p1mon/www/util/p1mon-util.php';
include_once '/p1mon/www/util/page_menu_header_cost.php';
include_once '/p1mon/www/util/page_menu.php';
include_once '/p1mon/www/util/check_display_is_active.php';
include_once '/p1mon/www/util/weather_info.php';
include_once '/p1mon/www/util/pageclock.php';
include_once '/p1mon/www/util/fullscreen.php';
include_once '/p1mon/www/util/highchart.php';

if (checkDisplayIsActive(19) == false) {
    return;
}
?>
<!doctype html>
<html lang="<?php echo strIdx(370) ?>">
<head>
    <meta name="robots" content="noindex">
    <title>P1-monitor <?php echo strIdx(722) ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link type="text/css" rel="stylesheet" href="./css/p1mon.css">
    <link type="text/css" rel="stylesheet" href="./font/roboto/roboto.css">

    <script defer src="./font/awsome/js/all.js"></script>
    <script src="./js/jquery.min.js"></script>

    <script src="./js/highstock-link/highstock.js"></script>
    <script src="./js/highstock-link/highcharts-more.js"></script>
    <script src="./js/highstock-link/modules/accessibility.js"></script>

    <script src="./js/hc-global-options.js"></script>
    <script src="./js/p1mon-util.js"></script>
    <script src="./js/moment-link/moment-with-locales.min.js"></script>

    <script>
        var recordsLoaded     = 0;
        var initloadtimer;
        var mins              = 1;
        var secs              = mins * 60;
        var currentSeconds    = 0;
        var currentMinutes    = 0;
        var language_index    = '<?php echo config_read(148); ?>';

        var today_text        = '<?php echo strIdx(330); ?>';
        var yesterday_text    = '<?php echo strIdx(331); ?>';
        var tomorrow_text     = '<?php echo strIdx(332); ?>';

        var Gselected         = 1;
        var GseriesVisibilty  = [true, true];
        var GkwhData          = [];
        var GgasData          = [];

        var GkwhLookup        = {};
        var GgasLookup        = {};

        var displayPeriodList = [yesterday_text, today_text, tomorrow_text];
        var displayPeriodID   = 1; // 0 = yesterday, 1=today, 2=tomorrow

        var GtariffChart      = null;
        var GtimerTextElement = null;
        var GisReadingData    = false;
        var GchartCreated     = false;


        /* start aanpassing goedkoopste dynamische tarieven blok */
        var GdynamicTariffSelectedHours   = 2;
        var GdynamicTariffPeakWeight      = 0.12;
        var GdynamicTariffStabilityWeight = 0.50;

        var GdynamicTariffCheapestStart   = null;
        var GdynamicTariffCheapestEnd     = null;
        var GdynamicTariffExpensiveStart  = null;
        var GdynamicTariffExpensiveEnd    = null;
        var GdynamicTariffBestAvg         = null;
        var GdynamicTariffWorstAvg        = null;
        var GdynamicTariffDayAverage      = null;
        /* einde aanpassing goedkoopste dynamische tarieven blok */


        /*
        function decodeHtml(html) {
            var txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        }
        */


        function getTariffChart() {
            if (GtariffChart != null) {
                return GtariffChart;
            }

            GtariffChart = $('#TariffChart').highcharts();

            return GtariffChart;
        }

        function set_language() {
            switch (language_index) {
                case '1':
                    moment.locale('en');
                    break;
                case '2':
                    moment.locale('fr');
                    break;
                default:
                    moment.locale('nl');
            }
        }

        function setChartTitle(chartTitle) {
            var chart = getTariffChart();

            if (typeof(chart) !== 'undefined' && chart != null) {
                chart.setTitle({
                    text: chartTitle,
                    style: {
                        color: '#6E797C',
                        fontSize: '26px',
                        fontFamily: 'robotomedium',
                        fontWeight: 'bold'
                    }
                });
            }
        }

        function buildTariffRequestData() {
            var url_parameters = "";
            var chartTitle     = "";

            if (displayPeriodID == 0) { // yesterday
                url_parameters = "range=" + moment().add(-1, 'days').format('YYYY-MM-DD');
                chartTitle     = moment().add(-1, 'days').format('dddd DD MMMM YYYY') + " (" + yesterday_text + ")";
            } else if (displayPeriodID == 1) { // today
                url_parameters = "range=" + moment().format('YYYY-MM-DD');
                chartTitle     = moment().format('dddd DD MMMM YYYY') + " (" + today_text + ")";
            } else if (displayPeriodID == 2) { // tomorrow
                url_parameters = "range=" + moment().add(1, 'days').format('YYYY-MM-DD');
                chartTitle     = moment().add(1, 'days').format('dddd DD MMMM YYYY') + " (" + tomorrow_text + ")";
            } else {
                url_parameters = "range=" + moment().format('YYYY-MM-DD'); // default failsafe
                chartTitle     = moment().format('dddd DD MMMM YYYY');
            }

            return {
                url_parameters: url_parameters,
                chartTitle: chartTitle
            };
        }

        function formatTariffTime(timestampValue) {
            return moment(timestampValue).format('HH:mm');
        }

        /*

           Start pagina hack voor de Ztatz P1-Monitor om de goedkoopste uren weer te geven in
           blokken van 1,2,3,4,5 en 6 uur vanaf het actuele tijdstip in de dynamische tarieven.
           Originele pagina code P1-Monitor © securbro, https://ztatz.nl

           Copyright (c) Thunder-Ace-00 2026
           Vrij te gebruiken, aan te passen en te verspreiden, mits naamsvermelding behouden blijft.

        */


        function updateTariffSummaryText() {
            var elBlock = document.getElementById('tariff-lowcost-block');

            if (elBlock == null) {
                return;
            }

            if (
                GdynamicTariffCheapestStart == null ||
                GdynamicTariffCheapestEnd   == null ||
                GdynamicTariffBestAvg       == null
            ) {
                elBlock.innerHTML = '';
                return;
            }

            elBlock.innerHTML =
                formatTariffTime(GdynamicTariffCheapestStart) +
                ' tot ' +
                formatTariffTime(GdynamicTariffCheapestEnd) +
                ' uur - gemiddelde : &euro; ' +
                GdynamicTariffBestAvg.toFixed(2).replace('.', ',');
        }

        function updateDynamicTariffPeriodButtons() {
            var i;
            var buttonElement = null;

            for (i = 1; i <= 6; i++) {
                buttonElement = document.getElementById('tariff_period_' + i);

                if (buttonElement == null) {
                    continue;
                }

                if (i === Number(GdynamicTariffSelectedHours)) {
                    buttonElement.style.border = '2px solid #10D0E7';
                    buttonElement.style.opacity = '1';
                } else {
                    buttonElement.style.border = '';
                    buttonElement.style.opacity = '0.85';
                }
            }
        }


        function setDynamicTariffPeriod(periodHours) {
            GdynamicTariffSelectedHours = Number(periodHours);

            if (isNaN(GdynamicTariffSelectedHours)) {
                GdynamicTariffSelectedHours = 2;
            }

            if (GdynamicTariffSelectedHours < 1) {
                GdynamicTariffSelectedHours = 1;
            }

            if (GdynamicTariffSelectedHours > 6) {
                GdynamicTariffSelectedHours = 6;
            }

            toLocalStorage('cost-dynamic-h-period-hours', GdynamicTariffSelectedHours);

            analyzeDynamicTariffBlocks();
            updateTariffChartBands();
            updateDynamicTariffPeriodButtons();
        }


        function analyzeDynamicTariffBlocks() {
            GdynamicTariffCheapestStart  = null;
            GdynamicTariffCheapestEnd    = null;
            GdynamicTariffExpensiveStart = null;
            GdynamicTariffExpensiveEnd   = null;
            GdynamicTariffBestAvg        = null;
            GdynamicTariffWorstAvg       = null;
            GdynamicTariffDayAverage     = null;

            if (GkwhData.length === 0) {
                updateTariffSummaryText();
                return;
            }

            var kwhOnlyHours = [];
            var i;

            for (i = 0; i < GkwhData.length; i++) {
                if (
                    GkwhData[i] != null &&
                    GkwhData[i].length >= 2 &&
                    GkwhData[i][1] != null &&
                    isNaN(GkwhData[i][1]) === false
                ) {
                    kwhOnlyHours.push({
                        ts: GkwhData[i][0],
                        price: Number(GkwhData[i][1])
                    });
                }
            }

            if (kwhOnlyHours.length === 0) {
                updateTariffSummaryText();
                return;
            }

            var sumDay = 0;

            for (i = 0; i < kwhOnlyHours.length; i++) {
                sumDay += kwhOnlyHours[i].price;
            }

            GdynamicTariffDayAverage = sumDay / kwhOnlyHours.length;

            var currentHourStart = moment().minutes(0).seconds(0).milliseconds(0).valueOf();
            var candidateHours   = [];
            var useTodayFilter   = (displayPeriodID == 1);

            for (i = 0; i < kwhOnlyHours.length; i++) {
                if (useTodayFilter === true) {
                    if (kwhOnlyHours[i].ts >= currentHourStart) {
                        candidateHours.push(kwhOnlyHours[i]);
                    }
                } else {
                    candidateHours.push(kwhOnlyHours[i]);
                }
            }

            var blockSize = Math.max(1, Math.min(6, Number(GdynamicTariffSelectedHours) || 2));

            if (candidateHours.length < blockSize) {
                updateTariffSummaryText();
                return;
            }

            var lowestScore  = Infinity;
            var highestScore = -Infinity;
            var lowestIndex  = 0;
            var highestIndex = 0;
            var bestAvg      = 0;
            var worstAvg     = 0;
            var windowSum    = 0;
            var j;

            for (i = 0; i < blockSize; i++) {
                windowSum += candidateHours[i].price;
            }

            function calculateScore(startIndex, sumValue) {
                var avg = sumValue / blockSize;
                var maxPrice = -Infinity;
                var minPrice = Infinity;
                var price    = 0;

                for (j = 0; j < blockSize; j++) {
                    price = candidateHours[startIndex + j].price;

                    if (price > maxPrice) {
                        maxPrice = price;
                    }

                    if (price < minPrice) {
                        minPrice = price;
                    }
                }

                var range = maxPrice - minPrice;

                var score =
                    avg +
                    (maxPrice * GdynamicTariffPeakWeight) +
                    (range * GdynamicTariffStabilityWeight);

                return {
                    avg: avg,
                    score: score
                };
            }

            var firstResult = calculateScore(0, windowSum);

            lowestScore  = firstResult.score;
            highestScore = firstResult.score;
            lowestIndex  = 0;
            highestIndex = 0;
            bestAvg      = firstResult.avg;
            worstAvg     = firstResult.avg;

            for (i = 1; i <= candidateHours.length - blockSize; i++) {
                windowSum =
                    windowSum -
                    candidateHours[i - 1].price +
                    candidateHours[i + blockSize - 1].price;

                var result = calculateScore(i, windowSum);

                if (result.score < lowestScore) {
                    lowestScore = result.score;
                    lowestIndex = i;
                    bestAvg = result.avg;
                }

                if (result.score > highestScore) {
                    highestScore = result.score;
                    highestIndex = i;
                    worstAvg = result.avg;
                }
            }

            GdynamicTariffCheapestStart  = candidateHours[lowestIndex].ts;
            GdynamicTariffCheapestEnd    = GdynamicTariffCheapestStart + (blockSize * 3600000);
            GdynamicTariffExpensiveStart = candidateHours[highestIndex].ts;
            GdynamicTariffExpensiveEnd   = GdynamicTariffExpensiveStart + (blockSize * 3600000);
            GdynamicTariffBestAvg        = bestAvg;
            GdynamicTariffWorstAvg       = worstAvg;

            updateTariffSummaryText();
        }


        function updateTariffChartBands() {
            var chart = getTariffChart();

            if (typeof(chart) === 'undefined' || chart == null) {
                return;
            }

            if (!chart.xAxis || !chart.xAxis[0] || !chart.yAxis || !chart.yAxis[0]) {
                return;
            }

            var xAxis = chart.xAxis[0];
            var yAxis = chart.yAxis[0];

            xAxis.removePlotBand('cheapest');
            xAxis.removePlotBand('expensive');
            xAxis.removePlotLine('nowLine');
            yAxis.removePlotLine('avgLine');

            if (
                GdynamicTariffCheapestStart != null &&
                GdynamicTariffCheapestEnd != null
            ) {
                xAxis.addPlotBand({
                    id: 'cheapest',
                    from: GdynamicTariffCheapestStart,
                    to: GdynamicTariffCheapestEnd,
                    color: 'rgba(25,135,84,0.30)'
                });
            }

            if (
                GdynamicTariffExpensiveStart != null &&
                GdynamicTariffExpensiveEnd != null
            ) {
                xAxis.addPlotBand({
                    id: 'expensive',
                    from: GdynamicTariffExpensiveStart,
                    to: GdynamicTariffExpensiveEnd,
                    color: 'rgba(220,53,69,0.25)'
                });
            }

            if (displayPeriodID == 1) {
                xAxis.addPlotLine({
                    id: 'nowLine',
                    value: new Date().getTime(),
                    color: '#0000ff',
                    width: 1,
                    dashStyle: 'Dash'
                });
            }

            if (GdynamicTariffDayAverage != null) {
                yAxis.addPlotLine({
                    id: 'avgLine',
                    value: GdynamicTariffDayAverage,
                    color: '#ffc107',
                    width: 1,
                    dashStyle: 'ShortDash'
                });
            }

            chart.redraw();
        }


        function readJsonApiTariffHour() {
            if (GisReadingData === true) {
                return;
            }

            GisReadingData = true;

            var requestData    = buildTariffRequestData();
            var url_parameters = requestData.url_parameters;
            var chartTitle     = requestData.chartTitle;

            setChartTitle(chartTitle);

            $.ajax({
                url      : "/api/v1/financial/dynamic_tariff?" + url_parameters,
                method   : "GET",
                dataType : "text",
                cache    : false
            })
            .done(function(data) {
                try {
                    var jsondata = JSON.parse(data);
                    var item;

                    GkwhData.length = 0;
                    GgasData.length = 0;

                    GkwhLookup = {};
                    GgasLookup = {};

                    recordsLoaded = jsondata.length;

                    for (var j = jsondata.length; j > 0; j--) {
                        item    = jsondata[j - 1];
                        item[1] = item[1] * 1000; // highchart likes millisecs.

                        GkwhData.push([item[1], item[2]]);
                        GgasData.push([item[1], item[3]]);

                        GkwhLookup[item[1]] = item[2];
                        GgasLookup[item[1]] = item[3];
                    }

                    updateData();

                    /* nieuw toegevoegd advies dynamische tarieven */
                    analyzeDynamicTariffBlocks();
                    updateTariffChartBands();

                } catch (err) {
                    console.log(err);
                }
            })
            .fail(function(jqxhr, textStatus, errorThrown) {
                console.log("readJsonApiTariffHour() failed:", textStatus, errorThrown);
            })
            .always(function() {
                GisReadingData = false;
            });
        }


        function setDateRange(periodID) {
            displayPeriodID = periodID;
            readJsonApiTariffHour();
            toLocalStorage('cost-dynamic-h-day', displayPeriodID);
        }

        /*

           Einde pagina hack voor de Ztatz P1-Monitor om de goedkoopste uren weer te geven in
           blokken van 1,2,3,4,5 en 6 uur vanaf het actuele tijdstip in de dynamische tarieven.

           Copyright (c) Thunder-Ace-00 2026
           Vrij te gebruiken, aan te passen en te verspreiden, mits naamsvermelding behouden blijft.

        */


        function updateData() {
            var chart = getTariffChart();

            if (typeof(chart) !== 'undefined' && chart != null) {
                if (chart.series[0]) {
                    chart.series[0].setData(GkwhData, false);
                }

                if (chart.series[1]) {
                    chart.series[1].setData(GgasData, false);
                }

                chart.redraw();
            }
        }


        // change items with the marker #PARAMETER
        function createTariffChart() {
            Highcharts.chart('TariffChart', {
                chart: {
                    style: {
                        fontFamily: 'robotomedium',
                        fontSize: '14px'
                    },
                    backgroundColor: '#ffffff',
                    renderTo: 'container',
                    type: 'column',
                    borderWidth: 0
                },
                legend: {
                    x: 0,
                    y: 0,
                    symbolHeight: 12,
                    symbolWidth: 12,
                    symbolRadius: 3,
                    borderRadius: 5,
                    borderWidth: 1,
                    backgroundColor: '#DCE1E3',
                    symbolPadding: 3,
                    enabled: true,
                    align: 'right',
                    verticalAlign: 'top',
                    layout: 'horizontal',
                    floating: true,
                    itemStyle: {
                        color: '#6E797C'
                    },
                    itemHoverStyle: {
                        color: '#10D0E7'
                    },
                    itemDistance: 5
                },
                plotOptions: {
                    series: {
                        minPointLength: 1, // hack to show tooltip in zero values.
                        events: {
                            legendItemClick: function(event) {
                                if (this.index === 0) {
                                    toLocalStorage('cost-dynamic-h-day-kwh', this.visible); // #PARAMETER
                                }

                                if (this.index === 1) {
                                    toLocalStorage('cost-dynamic-h-day-gas', this.visible); // #PARAMETER
                                }
                            }
                        }
                    }
                },
                exporting: {
                    enabled: false
                },
                xAxis: {
                    labels: {
                        step: 1
                    },
                    minRange: 24,
                    tickInterval: 3600000,
                    type: 'datetime',
                    dateTimeLabelFormats: {
                        day: '%H:%M',
                        hour: '%H:%M'
                    },
                    lineColor: '#6E797C',
                    lineWidth: 1
                },
                yAxis: [
                    {
                        tickInterval: 0.05,
                        title: false,
                        gridLineColor: '#6E797C',
                        gridLineDashStyle: 'longdash',
                        lineWidth: 0,
                        offset: 0,
                        opposite: false,
                        labels: {
                            format: '€ {value:.2f}',
                            style: {
                                color: '#6E797C'
                            }
                        },
                        plotLines: [
                            {
                                value: 0,
                                width: 1,
                                color: '#6E797C'
                            }
                        ]
                    },
                    {
                        title: false,
                        opposite: true,
                        gridLineDashStyle: 'longdash',
                        gridLineColor: '#6E797C',
                        gridLineWidth: 1,
                        labels: {
                            format: '{value}°C',
                            style: {
                                color: '#384042'
                            }
                        }
                    }
                ],
                tooltip: {
                    useHTML: false,
                    style: {
                        padding: 3,
                        color: '#6E797C'
                    },
                    formatter: function() {
                        var s = '<b>' + Highcharts.dateFormat('%A<br>%Y-%m-%d %H:%M-%H:59', this.x) + '</b>';

                        var kwh_cost = null;
                        var gas_cost = null;

                        if (typeof GkwhLookup[this.key] !== 'undefined') {
                            kwh_cost = GkwhLookup[this.key];
                        }

                        if (typeof GgasLookup[this.key] !== 'undefined') {
                            gas_cost = GgasLookup[this.key];
                        }

                        if (getTariffChart().series[0].visible === true && kwh_cost != null) {
                            s += '<br/><span style="color: #ff6f49;">kWh:&nbsp;</span>&nbsp;&euro;&nbsp' + kwh_cost.toFixed(2);
                        }

                        if (getTariffChart().series[1].visible === true && gas_cost != null) {
                            s += '<br/><span style="color: #cc5637;">Gas:&nbsp;</span>&nbsp;&euro;&nbsp' + gas_cost.toFixed(2);
                        }

                        return s;
                    },
                    backgroundColor: '#F5F5F5',
                    borderColor: '#DCE1E3',
                    crosshairs: [true, true],
                    borderWidth: 1
                },
                series: [
                    {
                        yAxis: 0,
                        id: 'verbruik',
                        visible: GseriesVisibilty[0],
                        name: '<?php echo strIdx(760); ?>',
                        color: '#ff6f49',
                        data: GkwhData
                    },
                    {
                        yAxis: 0,
                        id: 'geleverd',
                        visible: GseriesVisibilty[1],
                        name: '<?php echo strIdx(761); ?>',
                        color: '#cc5637',
                        data: GgasData
                    }
                ],
                lang: {
                    noData: "Geen gegevens beschikbaar."
                },
                noData: {
                    style: {
                        fontFamily: 'robotomedium',
                        fontWeight: 'bold',
                        fontSize: '25px',
                        color: '#10D0E7'
                    }
                }
            });

            GtariffChart  = $('#TariffChart').highcharts();
            GchartCreated = true;
        }


        function DataLoop() {
            currentMinutes = Math.floor(secs / 60);
            currentSeconds = secs % 60;

            if (currentSeconds <= 9) {
                currentSeconds = "0" + currentSeconds;
            }

            secs--;

            if (GtimerTextElement != null) {
                GtimerTextElement.innerHTML = zeroPad(currentMinutes, 2) + ":" + zeroPad(currentSeconds, 2);
            }

            if (GchartCreated === false || getTariffChart() == null) {
                hideStuff('loading-data');
                createTariffChart();
                readJsonApiTariffHour();
            } else if (secs < 0) {
                mins           = 1;
                secs           = mins * 60;
                currentSeconds = 0;
                currentMinutes = 0;

                colorFader("#timerText", "#0C7DAD");
                readJsonApiTariffHour();
            }

            setTimeout(DataLoop, 1000);
        }


        $(function() {
            set_language();

            GtimerTextElement = document.getElementById("timerText");

            toLocalStorage('cost-menu', window.location.pathname);

            if (getLocalStorage('cost-dynamic-h-day') != null) {
                displayPeriodID = parseInt(getLocalStorage('cost-dynamic-h-day'), 10);

                if (isNaN(displayPeriodID)) {
                    displayPeriodID = 1;
                }
            }

            var storedDynamicTariffPeriodHours = getLocalStorage('cost-dynamic-h-period-hours');

            if (storedDynamicTariffPeriodHours != null) {
                GdynamicTariffSelectedHours = parseInt(storedDynamicTariffPeriodHours, 10);

                if (isNaN(GdynamicTariffSelectedHours)) {
                    GdynamicTariffSelectedHours = 2;
                }
            }

            if (GdynamicTariffSelectedHours < 1) {
                GdynamicTariffSelectedHours = 1;
            }

            if (GdynamicTariffSelectedHours > 6) {
                GdynamicTariffSelectedHours = 6;
            }

            var storedKwhVisibility = getLocalStorage('cost-dynamic-h-day-kwh');
            var storedGasVisibility = getLocalStorage('cost-dynamic-h-day-gas');

            if (storedKwhVisibility != null) {
                GseriesVisibilty[0] = JSON.parse(storedKwhVisibility); // #PARAMETER
            }

            if (storedGasVisibility != null) {
                GseriesVisibilty[1] = JSON.parse(storedGasVisibility); // #PARAMETER
            }

            if (typeof GseriesVisibilty[0] !== 'boolean') {
                GseriesVisibilty[0] = true;
            }

            if (typeof GseriesVisibilty[1] !== 'boolean') {
                GseriesVisibilty[1] = true;
            }

            Highcharts.setOptions({
                global: {
                    useUTC: false
                },
                time: {
                    useUTC: false
                },
                lang: <?php hc_language_json(); ?>
            });

            secs = 0;

            screenSaver(<?php echo config_read(79); ?>); // to enable screensaver for this screen.
            DataLoop();

            // set buttons text in the right language
            document.getElementById("yesterday_button").innerHTML = yesterday_text;
            document.getElementById("today_button").innerHTML     = today_text;
            document.getElementById("tomorrow_button").innerHTML  = tomorrow_text;

            updateDynamicTariffPeriodButtons();

        });
    </script>
</head>
<body>

<?php page_header(); ?>

<div class="top-wrapper-2">
    <div class="content-wrapper pad-13">
        <!-- header 2 -->
        <?php pageclock(); ?>
        <?php page_menu_header_cost(3); ?>
        <?php weather_info(); ?>
    </div>
</div>

<div class="mid-section">
    <div class="left-wrapper">
        <?php page_menu(4); ?>
        <div id="timerText" class="pos-8 color-timer"></div>
        <?php fullscreen(); ?>
    </div>

    <div class="mid-content-2 pad-13">
        <!-- links -->
        <div class="frame-2-top">

            <span class="text-2">
              <?php echo ucfirst(strIdx(723)) ?>
            </span>&nbsp;<span class="text-2" id="title_date"></span>

            <span class="float-right">
                <button id="yesterday_button" onclick="setDateRange(0)" class="button-4 bold-font color-menu"></button>
                <button id="today_button"     onclick="setDateRange(1)" class="button-4 bold-font color-menu"></button>
                <button id="tomorrow_button"  onclick="setDateRange(2)" class="button-4 bold-font color-menu"></button>
            </span>

        </div>

        <div class="frame-2-bot">
            <div id="TariffChart" style="width:100%; height:480px;"></div>
        </div>
        <div class="center">
          <span class="text-3">Stroom goedkoopste periode van </span>
          <span id="tariff-lowcost-block" class="text-3"></span>
        </div>

        <div class="center" style="padding-top:10px;">
            <span class="text-3">Stroom periode kiezen : </span>&nbsp;&nbsp;
            <button id="tariff_period_1" onclick="setDynamicTariffPeriod(1)" class="button-4 bold-font color-menu">1 uur</button>
            <button id="tariff_period_2" onclick="setDynamicTariffPeriod(2)" class="button-4 bold-font color-menu">2 uur</button>
            <button id="tariff_period_3" onclick="setDynamicTariffPeriod(3)" class="button-4 bold-font color-menu">3 uur</button>
            <button id="tariff_period_4" onclick="setDynamicTariffPeriod(4)" class="button-4 bold-font color-menu">4 uur</button>
            <button id="tariff_period_5" onclick="setDynamicTariffPeriod(5)" class="button-4 bold-font color-menu">5 uur</button>
            <button id="tariff_period_6" onclick="setDynamicTariffPeriod(6)" class="button-4 bold-font color-menu">6 uur</button>
        </div>

    </div>
</div>

<div id="loading-data">
    <img src="./img/ajax-loader.gif" alt="<?php echo strIdx(295) ?>" height="15" width="128">
</div>

</body>
</html>
