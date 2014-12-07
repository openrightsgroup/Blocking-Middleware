function updateResultsTable(response) {
	var jsonRows;

	jsonRows = response.match(/[^\r\n]+/g);
	$(jsonRows).each(function () {
		var notification, isp_tr, updated;
		
		notification = JSON.parse(this);
		if (notification.network_name) {
			$("#results tbody tr").each(function () {
				var isp_name = $(this).find("td:first").html();
				if (isp_name == notification.network_name)
					isp_tr = $(this);
			});
			if (typeof isp_tr === "undefined") {
				isp_tr = $("<tr>");
				isp_tr.appendTo($("#results tbody"));
			}
			updated = isp_tr.children("td:nth-child(3)").html() != notification.status_timestamp;
			isp_tr.children().remove();
			isp_tr.attr("class", (updated ? "updated " : "")
					+ (notification.status === "ok" ?
							(notification.last_blocked_timestamp === null ? "success" : "warning")
							: "danger"));
			isp_tr.append(
					"<td>" + notification.network_name + "</td>"
					+ "<td>" + notification.status + "</td>"
					+ "<td>" + notification.status_timestamp + "</td>"
					+ "<td>"
					+ ((notification.last_blocked_timestamp === null)
							? "No record of prior block"
							: notification.last_blocked_timestamp)
					+ "</td>");
			setTimeout(function () {
				isp_tr.removeClass("updated")
			}, 500);
		}
	});
}

function updateResultsFromStream(url) {
	var last_response_len = false;
	var has_progress = false;
	$.ajax({
		url: ($("body").hasClass("exampleclient") ?
				"/example-client/example-realtime-helper.php?url="
				: "/resultshelper?url=") + url,
		dataType: "text",
		xhrFields: {
			onprogress: function (e) {
				var this_response, response = e.currentTarget.response;
				if (last_response_len === false)
				{
					this_response = response;
					last_response_len = response.length;
				}
				else
				{
					this_response = response.substring(last_response_len);
					last_response_len = response.length;
				}
				updateResultsTable(this_response);
				has_progress = true;
			}
		}
	})
	.done(function (data, status, xhr) {
		if(!has_progress)
			updateResultsTable(data);
		$("#loading").remove();
	});
}

$(document).ready(function () {
	if ($('table#results').size())
		updateResultsFromStream($('#4DRXNE97LE').val())
});
