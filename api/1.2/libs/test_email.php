<?php

include "services.php";

$r = new ContactLoader(0);

function checkok($email) {
    global $r;
    print($email . ":" . $r->validateEmail($email) . "\n");
}

checkok('foo@bar.com');
checkok('foo+apt@bar.com');
checkok('foo+apt@bar.co.uk');
checkok('foo+apt@bar.media');
checkok('foosd0s9ids9jdsd09csdc@bar.media');
checkok('foosd0s9ids9jdsd09csdc@bar9.media');

function checkerror($email) {
    global $r;
    try {
        $r->validateEmail($email);
    } catch (InvalidEmailError $e) {
        print($email . ":" . true . "\n");
        return;
    }
    print($email . ":" . false . "\n");
}

print("# error\n");
checkerror('foo@sdfsdf');
checkerror('foo@sdfsdf.com09sdcs093L');
checkerror('sdfsdf.com09sdcs093L');
checkerror(' @sdfsdf.com');
checkerror(' @sdfsdf.com');
