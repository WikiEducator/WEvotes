$(function () {
    var weAPI = "/api.php";

    function notLoggedIn() {
        if (wgUserName) {
            return false;
        }
        alert("You must be logged in to vote.");
        return true;
    }

    function API(data, success, failure) {
        data.action || (data.action = "query");
        data.format || (data.format = "json");
        $.ajax({
            url: window.wgServer + weAPI,
            type: "POST",
            data: data,
            success: success,
            error: failure,
        });
    }

    function doVote(pid, vid, vote) {
        API(
            {
                action: "wevotes",
                vopid: pid,
                vovid: vid,
                vovote: vote,
                vopage: wgArticleId,
                vomode: "vote",
            },
            function () {
                loadVotes(pid);
            },
            function () {
                alert("Unable to capture your vote.\nPlease try later.");
            },
        );
    }

    function loadVotes(pid) {
        API(
            {
                action: "wevotes",
                vopid: pid,
                vomode: "get",
            },
            function (d) {
                if (d && d.wevotes) {
                    var res = d.wevotes;

                    // Reset all vote elements' colors to black first
                    $(".wevotedown, .wevotezero, .wevoteup").css(
                        "color",
                        "black",
                    );

                    // Update vote totals on page
                    if (res.totals) {
                        $.each(res.totals, function (vid, value) {
                            $("div#wevi_" + vid + ">.wevotes").text(value);
                        });

                        // Recalculate sums for span.WEvotesTotal
                        $("span.WEvotesTotal").each(function () {
                            var sum = 0;
                            $(this)
                                .closest("li")
                                .find("div.wevotes")
                                .each(function () {
                                    var v = $.trim($(this).text());
                                    if (v.length) {
                                        sum += parseInt(v, 10);
                                    }
                                });
                            $(this).text(sum);
                        });
                    }

                    // Highlight current user's votes
                    if (res.myvotes) {
                        $.each(res.myvotes, function (vid, vote) {
                            showVote(vid, vote);
                        });
                    }
                }
            },
            function () {
                // Handle loading error silently or fallback
            },
        );
    }

    function showVote(vid, vote) {
        var $vid = $("div#wevi_" + vid);
        switch (vote) {
            case -1:
                $vid.find(".wevotedown").css("color", "orange");
                $vid.find(".wevotezero, .wevoteup").css("color", "black");
                break;
            case 0:
                $vid.find(".wevotezero").css("color", "orange");
                $vid.find(".wevotedown, .wevoteup").css("color", "black");
                break;
            case 1:
                $vid.find(".wevoteup").css("color", "orange");
                $vid.find(".wevotedown, .wevotezero").css("color", "black");
                break;
        }
    }

    // Initial load of votes for the page
    loadVotes(1);

    $(".wevotedown,.wevotezero,.wevoteup").css("cursor", "pointer");
    $(".wevotedown").attr("title", "vote down");
    $(".wevoteup").attr("title", "vote up");

    $(".wevotedown").click(function (e) {
        if (notLoggedIn()) {
            return false;
        }
        var vid = $(e.target).parent().parent().attr("id").split("_")[1];
        showVote(vid, -1);
        doVote(1, vid, -1);
        return false;
    });
    $(".wevotezero").click(function (e) {
        if (notLoggedIn()) {
            return false;
        }
        var vid = $(e.target).parent().parent().attr("id").split("_")[1];
        showVote(vid, 0);
        doVote(1, vid, 0);
        return false;
    });
    $(".wevoteup").click(function (e) {
        if (notLoggedIn()) {
            return false;
        }
        var vid = $(e.target).parent().parent().attr("id").split("_")[1];
        showVote(vid, 1);
        doVote(1, vid, 1);
        return false;
    });
});
