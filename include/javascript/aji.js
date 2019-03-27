function addEvent( element, types, handler )
{
    if ( typeof types === "object" )
    {
        var type;
        for ( type in types )
        {
            addEvent( element, types[type], handler );
        }
        return element;
    }
    if ( element.addEventListener )
    {
        element.addEventListener( types, handler, false );
    }
    else if ( element.attachEvent )
    {
        element.attachEvent( 'on' + types, handler );
    }
    else
    {
        return false;
    }
}

var httpRequest = ( "onload" in new XMLHttpRequest() ) ? XMLHttpRequest : XDomainRequest;
