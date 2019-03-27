<!DOCTYPE html>
<html>
    <head>
        <title>Depkasa payment</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="include/css/normalize.css">
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="include/css/main.css?<?= date( 'YdmHis' ) ?>">
        <script src="include/javascript/aji.js?<?= date( 'YdmHis' ) ?>"></script>
        <script src="include/javascript/main.js?<?= date( 'YdmHis' ) ?>"></script>
        <style>
            .console{
                width: 100%;
            }
        </style>
    </head>
    <body>
        <div id="main">
            <div class="header">
                <h3 class="center">Формирование запроса payment в MOCK и обработка ответа.</h3>
            </div>
            <div class="content">
                <div class="pure-g">
                    <div class="pure-u-1-2">
                        <form
                            id="payment_form"
                            onsubmit="return send_payment( this.id );">
                            <div class="pure-g">
                                <label class="pure-u-1-3" for="amount">Введите сумму:</label>
                                <div class="pure-u-1-3">
                                    <input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        value="500.00"
                                        step="0.01"
                                        min="0"
                                        >
                                </div>
                                <div class="pure-u-1-3">
                                    <input
                                        id="submit"
                                        name="submit"
                                        type="submit"
                                        value="Оплатить"
                                        >
                                </div>
                            </div>

                        </form>

                    </div>
                    <div class="pure-u-1-2">
                        <label>Выполнение платежа: <span id="processing"></span> <br/>
                            <textarea id="console" class="console" readonly="" rows="20"></textarea>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
