var UsernameFlag = false;

function checkUsername(){
	var username = document.getElementById("username").value;
	document.getElementById("nameErr").innerHTML = "";
	document.getElementById("question").innerHTML = "";
	$.post(postUrl1, {username: username}, function(result){
		if (result.state == true) {
			document.getElementById("question").innerHTML = result.message;
			UsernameFlag = true;
		} else {
			var tip = layer.msg(result.message);
			setTimeout(function(){
				layer.close(tip);
				}, 1000);
			document.getElementById("nameErr").innerHTML = result.message;
			UsernameFlag = false;
		}
	});
}

$(function(){
	$("#submitButton").click(function(){
		if (UsernameFlag) {
			var username = document.getElementById("username").value;
			var password = document.getElementById("password").value;
			var repassword = document.getElementById("password2").value;
			var answer = document.getElementById("answer").value;
			document.getElementById("Error").innerHTML = "";
			$.post(postUrl2, {username: username, password: password, repassword: repassword, answer: answer}, 
				function(result){
					if (result.state == true) {
						location.href = 'success_jump/message/' + result.message + '/controller/' + result.callback['controller'] + '/function/' + result.callback['function'];
					} else {
						var tip = layer.msg(result.message);
						setTimeout(function(){
							layer.close(tip);
							}, 1000);
						document.getElementById("Error").innerHTML = result.message;
					}
				}
			);
		} else {
			var tip = layer.msg("请检查用户名");
			setTimeout(function(){
				layer.close(tip);
				}, 1000);
			document.getElementById("Error").innerHTML = "请检查用户名";
		}
	});
});