var startTime;
var outStr;
function send_payment( form_name )
{
    var form = document.forms[form_name];
    var amount = form.elements['amount'].value;
    var submit = form.elements['submit'];
    if ( amount == '' || amount <= 0.0 )
    {
        alert( "Введите положительную сумму платежа" );
        return false;
    }

    document.getElementById( 'console' ).innerHTML = '';
    submit.disabled = true;

    var XMLsendPayment = new httpRequest();
    XMLsendPayment.onprogress = function( event )
    {
        //console.log( this.response.trim() + "}" );

        var response = this.response.trim() + ']';
        response = JSON.parse( response );
        var msg;
        while ( outStr < response.length - 1 )
        {
            outStr ++;
            msg = response[outStr].msg;
            if ( msg != '' )
            {
                document.getElementById( 'console' ).innerHTML += msg + '\n';
            }
        }
        timeExp = Math.floor( ( Date.now() - startTime ) / 1000 );
        var minutes = Math.floor( timeExp / 60 );
        var seconds = timeExp - minutes * 60;
        if ( minutes < 0 )
        {
            minutes ++;
            seconds = 60 - seconds;
        }

        minutes = "0" + minutes;
        seconds = "0" + seconds;

        document.getElementById( 'processing' ).innerHTML = minutes.substr( - 2 ) + ':' + seconds.substr( - 2 );


    }
    XMLsendPayment.onreadystatechange = function()
    {
        if ( XMLsendPayment.readyState != 4 )
        {
            return;
        }
        submit.disabled = false;

        if ( XMLsendPayment.status != 200 )
        {
            document.getElementById( 'console' ).innerHTML += 'Соединение потеряно... \n';
            console.error( XMLsendPayment.status + ': ' + XMLsendPayment.statusText );
        }
        else
        {
            document.getElementById( 'console' ).innerHTML += 'Соединение закрыто... \n';
            console.log( XMLsendPayment.responseText );
        }
    }

    var data = 'amount=' + amount;
    XMLsendPayment.open( 'POST', 'index.php?action=payment', true );
    XMLsendPayment.timeout = 3000000;
    //XMLsendPayment.setRequestHeader( 'Content-type', 'application/json; charset=utf-8' );
    XMLsendPayment.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
    startTime = Date.now();
    outStr = - 1;
    XMLsendPayment.send( data );
    //XMLsendPayment.send(  );

    return false;
}
