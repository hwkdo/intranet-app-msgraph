<?php

namespace Hwkdo\IntranetAppMsgraph\Enums;

enum AuslandseinsatzMembershipStatus: string
{
    case Geplant = 'geplant';
    case Aktiv = 'aktiv';
    case Abgelaufen = 'abgelaufen';
    case Entfernt = 'entfernt';
    case NichtVerwaltet = 'nicht_verwaltet';
}
