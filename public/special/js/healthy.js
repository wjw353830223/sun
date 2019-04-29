var data = OpenInstall.parseUrlParams();//openinstall.js中提供的工具函数，解析url中的所有查询参数
var m = new OpenInstall({
    appKey: 'uxtva4'
}, data);
m.schemeWakeup();

function getParam(paramName) {
    paramValue = "", isFound = !1;
    if (this.location.search.indexOf("?") == 0 && this.location.search.indexOf("=") > 1) {
        arrSource = unescape(this.location.search).substring(1, this.location.search.length).split("&"), i = 0;
        while (i < arrSource.length && !isFound) arrSource[i].indexOf("=") > 0 && arrSource[i].split("=")[0].toLowerCase() == paramName.toLowerCase() && (paramValue = arrSource[i].split("=")[1], isFound = !0), i++
    }
    return paramValue == "" && (paramValue = null), paramValue
}

function app_share(share_data) {
    if (getParam('type') === null) {
        $('.healthy-list').click(function () {
            var goods_commonid = $(this).find('input').val();
            var shareData = {
                method: 'jumpToProductDetail',
                params: {
                    goods_commonid: goods_commonid
                },
                callBackMethod: 'callBack'
            };
            var ua = navigator.userAgent.toLowerCase();
            if (/iphone|ipad|ipod/.test(ua)) {
                window.webkit.messageHandlers.utils.postMessage(shareData);
            } else if (/android/.test(ua)) {
                AndroidJs.jumpToProductDetail(goods_commonid);
            }
        });
    } else {
        $('.app-download').show();
        $("#downloadButton,.healthy-list").click(function () {
            m.install();
        });
        $.ajax({
            type: "get",
            url: "/api/wechat/wechat_share?url=" + encodeURIComponent(location.href.split('#')[0]),
            dataType: "json",
            success: function (data) {
                wx.config({
                    debug: false,
                    appId: data.result.appId,
                    timestamp: data.result.timestamp,
                    nonceStr: data.result.nonceStr,
                    signature: data.result.signature,
                    jsApiList: [
                        "onMenuShareTimeline",
                        "onMenuShareAppMessage",
                        "onMenuShareQQ",
                        "onMenuShareWeibo"
                    ]
                });
                wx.ready(function () {
                    wx.onMenuShareAppMessage(share_data);
                    wx.onMenuShareTimeline(share_data);
                    wx.onMenuShareQQ(share_data);
                    wx.onMenuShareWeibo(share_data);
                });
                wx.error(function (res) {
                    alert(res.errMsg);
                });
            }
        });
    }
}