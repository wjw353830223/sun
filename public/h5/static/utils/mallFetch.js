//处理必传参数和选传参数
function baseParams(params) {
  let signParams = []
  let mustParams
  if (ui.getStorageSync('token') == null || ui.getStorageSync('token') == undefined || ui.getStorageSync('token') == '') {
    mustParams = {
      'client_type': ui.IS_ANDROID ? "android" : "ios",
      '_timestamp': Math.round((new Date().getTime() + Math.random() * 100000) / 1000)
    }
  } else {
    mustParams = {
      'client_type': ui.IS_ANDROID ? "android" : "ios",
      '_timestamp': Math.round((new Date().getTime() + Math.random() * 100000) / 1000),
      'token': ui.getStorageSync('token')
    }
  }

  // 判断传参是否未定义，否则判断_timestamp是否未定义(主要用于支付接口中form表单的_timestamp)
  if (params !== undefined) {
    if (Object.keys(params).length > 0) {
      if (params._timestamp !== undefined) {
        mustParams._timestamp = params._timestamp
      }
    }
  }
  mustParams = Object.assign(mustParams, params)

  for (let [name, value] of Object.entries(mustParams)) {
    signParams.push({
      name,
      value
    })
  }

  signParams.sort(function (a, b) {
    return a.name > b.name ? 1 : a.name < b.name ? -1 : 0;
  });

  var sha2 = $.sha1($.param(signParams));

  mustParams = Object.assign(mustParams, {
    'signature': sha2
  });

  return mustParams
}


/**
 * 请求数据的方法
 * @param {string} path 接口地址 {/api/...}
 * @param {string} method 访问方式 {POST,GET...}
 * @param {object} params 传递的参数 {client_type,_timestamp,signature...}
 */
function fetchs(path, method, params) {
  //封装ui.request
  let lastParam = baseParams(params)

  return new Promise((resolve, reject) => {
    ui.request({
      url: `${path}`,
      method: method,
      data: Object.assign({}, lastParam),
      header: {
        'content-type': 'application/json'
      },
      success: resolve,
      fail: reject
    })

  })
}

/**
 * 商品详情页
 * @param {string} url 接口地址 {/api/...}
 * @param {number} goods_commonid 商品/产品id
 */
function fetchDetail(url, goods_commonid) {
  return new Promise((resolve, reject) => {
    ui.navigateTo({
      url: url + '?goods_commonid=' + goods_commonid,
      success: resolve,
      fail: reject
    })
  })
}

export {
  baseParams,
  fetchs,
  fetchDetail
}