
/* Tab-Container aufbauen **************************************************/

var a = new Array("uebersicht", "details", "zusaetze", "anlagen", "laufzettel");
var b = new Array("menueUebersicht", "menueDetails", "menueZusaetze", "menueAnlagen", "menueLaufzettel");

function show(e) {
  var i = 0;
  var j = 1;
  for (var t = 0; t < b.length; t++) {
    if (b[t] === e.id) {
      i = t;
      if (i > 0) {
        j = 0;
      } else {
        j = i + 1;
      }
      break;
    }
  }
  e.setAttribute("class", "btnAktiv");
  for (var k = 0; k < b.length; k++) {
    if (k === i && (document.getElementById(a[k]) != null)) {
      document.getElementById(a[k]).style.display = "block";
      if (i === j)
      j = i + 1;
    } else {
      if (document.getElementById(a[k]) != null) {
        document.getElementById(a[j]).style.display = "none";
        document.getElementById(b[j]).setAttribute("class", "btnInaktiv");
        j += 1;
      }
    }
  }
}
window.onload = function () {
  document.getElementById(b[0]).setAttribute("class", "btnAktiv");
}

/* Eingebettete Binaerdaten runterladen   ************************************/


function base64_to_binary (data) {
  var chars = atob(data);
  var bytes = new Array(chars.length);
  for (var i = 0; i < chars.length; i++) {
    bytes[i] = chars.charCodeAt(i);
  }
  return new Uint8Array(bytes);
}

function downloadData (element_id) {
  var data_element = document.getElementById(element_id);
  var mimetype = data_element.getAttribute('mimeType');
  var filename = data_element.getAttribute('filename');
  var text = data_element.innerHTML;
  var binary = base64_to_binary(text);
  var blob = new Blob([binary], {
    type: mimetype, size: binary.length
  });
  
  if (window.navigator && window.navigator.msSaveOrOpenBlob) {
    // IE
    window.navigator.msSaveOrOpenBlob(blob, filename);
  } else {
    // Non-IE
    var url = window.URL.createObjectURL(blob);
    window.open(url);
  }
}


/* Polyfill IE atob/btoa   ************************************/

(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([], function () {
      factory(root);
    });
  } else factory(root);
  // node.js has always supported base64 conversions, while browsers that support
  // web workers support base64 too, but you may never know.
})(typeof exports !== "undefined" ? exports: this, function (root) {
  if (root.atob) {
    // Some browsers' implementation of atob doesn't support whitespaces
    // in the encoded string (notably, IE). This wraps the native atob
    // in a function that strips the whitespaces.
    // The original function can be retrieved in atob.original
    try {
      root.atob(" ");
    }
    catch (e) {
      root.atob = (function (atob) {
        var func = function (string) {
          return atob(String(string).replace(/[\t\n\f\r ]+/g, ""));
        };
        func.original = atob;
        return func;
      })(root.atob);
    }
    return;
  }
  
  // base64 character set, plus padding character (=)
  var b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
  // Regular expression to check formal correctness of base64 encoded strings
  b64re = /^(?:[A-Za-z\d+\/]{4})*?(?:[A-Za-z\d+\/]{2}(?:==)?|[A-Za-z\d+\/]{3}=?)?$/;
  
  root.btoa = function (string) {
    string = String(string);
    var bitmap, a, b, c,
    result = "", i = 0,
    rest = string.length % 3; // To determine the final padding
    
    for (; i < string.length;) {
      if ((a = string.charCodeAt(i++)) > 255 || (b = string.charCodeAt(i++)) > 255 || (c = string.charCodeAt(i++)) > 255)
      throw new TypeError("Failed to execute 'btoa' on 'Window': The string to be encoded contains characters outside of the Latin1 range.");
      
      bitmap = (a << 16) | (b << 8) | c;
      result += b64.charAt(bitmap >> 18 & 63) + b64.charAt(bitmap >> 12 & 63) + b64.charAt(bitmap >> 6 & 63) + b64.charAt(bitmap & 63);
    }
    
    // If there's need of padding, replace the last 'A's with equal signs
    return rest ? result.slice(0, rest - 3) + "===".substring(rest): result;
  };
  
  root.atob = function (string) {
    // atob can work with strings with whitespaces, even inside the encoded part,
    // but only \t, \n, \f, \r and ' ', which can be stripped.
    string = String(string).replace(/[\t\n\f\r ]+/g, "");
    if (! b64re.test(string))
    throw new TypeError("Failed to execute 'atob' on 'Window': The string to be decoded is not correctly encoded.");
    
    // Adding the padding if missing, for semplicity
    string += "==".slice(2 - (string.length & 3));
    var bitmap, result = "", r1, r2, i = 0;
    for (; i < string.length;) {
      bitmap = b64.indexOf(string.charAt(i++)) << 18 | b64.indexOf(string.charAt(i++)) << 12 | (r1 = b64.indexOf(string.charAt(i++))) << 6 | (r2 = b64.indexOf(string.charAt(i++)));
      
      result += r1 === 64 ? String.fromCharCode(bitmap >> 16 & 255): r2 === 64 ? String.fromCharCode(bitmap >> 16 & 255, bitmap >> 8 & 255): String.fromCharCode(bitmap >> 16 & 255, bitmap >> 8 & 255, bitmap & 255);
    }
    return result;
  };
});