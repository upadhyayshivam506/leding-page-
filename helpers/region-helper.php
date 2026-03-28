<?php

declare(strict_types=1);

function getRegionByState($state)
{
    $north = [
        "Delhi",
        "Haryana",
        "Punjab",
        "Uttar Pradesh",
        "Rajasthan",
        "Himachal Pradesh",
        "Uttarakhand"
    ];

    $south = [
        "Tamil Nadu",
        "Karnataka",
        "Kerala",
        "Telangana",
        "Andhra Pradesh"
    ];

    $east = [
        "West Bengal",
        "Bihar",
        "Jharkhand",
        "Odisha",
        "Assam"
    ];

    $west = [
        "Maharashtra",
        "Gujarat",
        "Madhya Pradesh",
        "Chhattisgarh",
        "Goa"
    ];

    if (in_array($state, $north)) return "North";
    if (in_array($state, $south)) return "South";
    if (in_array($state, $east)) return "East";

    return "West / Others";
}
