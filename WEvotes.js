/* IE console shim */
if ( ! window.console ) {
  (function() {
    var names = ["log", "debug", "info", "warn", "error",
        "assert", "dir", "dirxml", "group", "groupEnd", "time",
        "timeEnd", "count", "trace", "profile", "profileEnd"],
        i, l = names.length;

    window.console = {};

    for ( i = 0; i < l; i++ ) {
      window.console[ names[i] ] = function() {};
    }
  }());
}

$(function () {
  // scheme, host:port
  var couchHost = 'http://v.oerfoundation.org:5984/',
      couchDB = 'votes',
      couchVoteTotals = couchHost + couchDB + '/_design/vote/_view/totals?',
      couchMyVotes = couchHost + couchDB + '/_design/vote/_view/myvotes?',
      weAPI = '/api.php';

  function notLoggedIn() {
    if (wgUserName) {
      return false;
    }
    alert('You must be logged in to vote.');
    return true;
  }

  function API(data, success, failure) {
    data.action || (data.action = 'query');
    data.format || (data.format = 'json');
    $.ajax({
      url: window.wgServer + weAPI,
      type: 'POST',
      data: data,
      success: success,
      failure: failure
    });
  }

  function doVote(pid, vid, vote) {
    API({
      action: 'wevotes',
      vopid: pid,
      vovid: vid,
      vovote: vote,
      vopage: wgArticleId
    },
    function() {
      updateTotals(pid);
    },
    function() {
      alert("Unable to capture your vote.\nPlease try later.");
    });
  }

  function makeCouchqs(options) {
    var i,
        optionList = [];

    for (i in options) {
      if (options.hasOwnProperty(i)) {
        optionList.push(i + '=' + encodeURIComponent(options[i]));
      }
    }
    return optionList.join('&');
  }

  function updateTotals(pid) {
    $.ajax({
      url: couchVoteTotals + makeCouchqs({
            startkey: '["'+pid+'"]',
            endkey: '["'+pid+'", {}]',
            group_level: 2
          }),
      cache: false,
      dataType: 'jsonp',
      success: function(d) {
        var i, rowl, vid;
        //console.log(d);
        rowl = d.rows.length;
        for (i=0; i<rowl; i++) {
          vid = d.rows[i].key[1];
          //console.log(vid, d.rows[i].value);
          $('div#wevi_' + vid + '>.wevotes').text(d.rows[i].value);
        }
        // if there are totals fields, calculate them
        $('span.WEvotesTotal').each(function() {
          var sum = 0;
          $(this).closest('li').find('div.wevotes').each(function() {
            var v;
            v = $.trim($(this).text());
            if (v.length) {
              sum += parseInt(v, 10);
            }
          });
          $(this).text(sum);
        });
      }
    });
  }
      
  function showVote(vid, vote) {
    var $vid;
    $vid = $('div#wevi_' + vid);
    //console.log(vid, vote, $vid);
    switch(vote) {
    case -1:
      //console.log(vid,'->votedown');
      $vid.find('.wevotedown').css('color', 'orange');
      $vid.find('.wevotezero, .wevoteup').css('color', 'black');
      break;
    case 0:
      //console.log(vid,'->votezero');
      $vid.find('.wevotezero').css('color', 'orange');
      $vid.find('.wevotedown, .wevoteup').css('color', 'black');
      break;
    case 1:
      //console.log(vid,'->voteup');
      $vid.find('.wevoteup').css('color', 'orange');
      $vid.find('.wevotedown, .wevotezero').css('color', 'black');
      break;
    }
  }

  function myVotes(pid) {
    $.ajax({
      url: couchMyVotes + makeCouchqs({
        key: '["' + pid + '","' + wgUserName + '"]'
        }),
      cache: false,
      dataType: 'jsonp',
      success: function(d) {
        var i, rowl, vid, $vid;
        //console.log('myvotes');
        //console.log(d);
        rowl = d.rows.length;
        for (i=0; i<rowl; i++) {
          showVote(d.rows[i].value[0], d.rows[i].value[1]);
        }
      }
    });
  }

  updateTotals(1);
  myVotes(1);

  $('.wevotedown,.wevotezero,.wevoteup').css('cursor', 'pointer');
  $('.wevotedown').attr('title', 'vote down');
  $('.wevoteup').attr('title', 'vote up');
  $('.wevotedown').click(function(e){
    if (notLoggedIn()) {
      return false;
    }
    vid = $(e.target).parent().parent().attr('id').split('_')[1];
    showVote(vid, -1); 
    doVote(1, vid, -1);
    return false;
  });
  $('.wevotezero').click(function(e){
    if (notLoggedIn()) {
      return false;
    }
    vid = $(e.target).parent().parent().attr('id').split('_')[1];
    showVote(vid, 0); 
    doVote(1, vid, 0);
    return false;
  });
  $('.wevoteup').click(function(e){
    if (notLoggedIn()) {
      return false;
    }
    vid = $(e.target).parent().parent().attr('id').split('_')[1];
    showVote(vid, 1); 
    doVote(1, vid, 1);
    return false;
  });

});
