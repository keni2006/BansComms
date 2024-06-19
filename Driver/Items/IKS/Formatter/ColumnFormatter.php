<?php

namespace Flute\Modules\BansComms\Driver\Items\IKS\Formatter;

use Flute\Modules\BansComms\Driver\Items\IKS\Contracts\ColumnFormatterInterface;

class ColumnFormatter implements ColumnFormatterInterface
{
    public function dateFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    let date = new Date(data * 1000);
                    return ("0" + (date.getMonth() + 1)).slice(-2) + "-" +
                           ("0" + date.getDate()).slice(-2) + "-" +
                           date.getFullYear() + " " +
                           ("0" + date.getHours()).slice(-2) + ":" +
                           ("0" + date.getMinutes()).slice(-2);
                }
                return data;
            }
        ';
    }

    public function typeFormatRender(): string
    {
        return '
            function(data, type) {
                if (type === "display") {
                    return data == "mutes" ? `<i class="type-icon ph-bold ph-microphone-slash"></i>` : `<i class="type-icon ph-bold ph-chat-circle-dots"></i>`;
                }
                return data;
            }
        ';
    }

    public function timeFormatRender(): string
    {
        return "
            function(data, type, full) {
                let time = full[12];
                let ends = full[11];
                let unbannedBy = full[14];

                if (unbannedBy == '1') {
                    return '<div class=\"ban-chip bans-unban\">'+ t(\"banscomms.table.unbaned\") +'</div>';
                } else if (time == '0') {
                    return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
                } else if (Date.now() >= Number(ends) * 1000 && time != '0') {
                    return '<div class=\"ban-chip bans-end\">' + secondsToReadable(time) + '</div>';
                } else {
                    return '<div class=\"ban-chip\">' + secondsToReadable(time) + '</div>';
                }
            }
        ";
    }

    public function timeFormatRenderBans(): string
    {
        return "
            function(data, type, full) {
                let time = full[11];
                let ends = full[10];
                let unbannedBy = full[13];

                if (unbannedBy == '1') {
                    return '<div class=\"ban-chip bans-unban\">'+ t(\"banscomms.table.unbaned\") +'</div>';
                } else if (time == '0') {
                    return '<div class=\"ban-chip bans-forever\">'+ t(\"banscomms.table.forever\") +'</div>';
                } else if (Date.now() >= Number(ends) * 1000 && time != '0') {
                    return '<div class=\"ban-chip bans-end\">' + secondsToReadable(time) + '</div>';
                } else {
                    return '<div class=\"ban-chip\">' + secondsToReadable(time) + '</div>';
                }
            }
        ";
    }
}
