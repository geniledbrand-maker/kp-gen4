--- a/assets/js/app.js
+++ b/assets/js/app.js
@@ -72,16 +72,46 @@ function money(value, currency = 'RUB') {
const symbol = currency === 'RUB' ? '₽' : currency;
return formatted + '\u00A0' + symbol; // неразрывный пробел
}
-
-async function post(action, payload) {
-    const body = new URLSearchParams({
-        action,
-        sessid: SESSID,
-        ...payload
-    });
-
-    const response = await fetch('', {
-        method: 'POST',
-        headers: {
-            'Content-Type': 'application/x-www-form-urlencoded'
-        },
-        body
-    });
-
-    return await response.json();
-}
+/**
+ * Универсальная функция POST-запроса к ajax.php
+ */
+async function post(action, payload = {}) {
+    try {
+        const response = await fetch('/local/tools/kp_gen4/ajax.php', {
+            method: 'POST',
+            headers: {
+                'Content-Type': 'application/json'
+            },
+            body: JSON.stringify({
+                action,
+                sessid: SESSID,
+                ...payload
+            })
+        });
+
+        if (!response.ok) {
+            throw new Error(`HTTP ${response.status}`);
+        }
+
+        const text = await response.text();
+
+        // Проверяем, не пришёл ли HTML (ошибка/битрикс)
+        if (text.trim().startsWith('<')) {
+            console.error('❌ Ответ не JSON:', text.slice(0, 200));
+            throw new Error('Сервер вернул HTML вместо JSON');
+        }
+
+        return JSON.parse(text);
+    } catch (err) {
+        console.error('Ошибка в post():', err);
+        toast('Ошибка сети или сервера', true);
+        return { success: false, error: err.message };
+    }
+}
@@ -225,11 +255,19 @@ function postForm(action, payload) {
const form = document.createElement('form');
form.method = 'POST';
-    form.action = '';
+    form.action = '/local/tools/kp_gen4/ajax.php';
form.target = '_blank';
-
-    const actionInput = document.createElement('input');
-    actionInput.type = 'hidden';
-    actionInput.name = 'action';
-    actionInput.value = action;
-    form.appendChild(actionInput);
-
-    const sessidInput = document.createElement('input');
-    sessidInput.type = 'hidden';
-    sessidInput.name = 'sessid';
-    sessidInput.value = SESSID;
-    form.appendChild(sessidInput);
-
-    for (const [key, value] of Object.entries(payload)) {
-        const input = document.createElement('input');
-        input.type = 'hidden';
-        input.name = key;
-        input.value = typeof value === 'string' ? value : JSON.stringify(value);
-        form.appendChild(input);
-    }
-
-    document.body.appendChild(form);
-    form.submit();
-    document.body.removeChild(form);
+    const inputs = {
+        action,
+        sessid: SESSID,
+        ...payload
+    };
+
+    for (const [key, value] of Object.entries(inputs)) {
+        const input = document.createElement('input');
+        input.type = 'hidden';
+        input.name = key;
+        input.value = typeof value === 'string' ? value : JSON.stringify(value);
+        form.appendChild(input);
+    }
+
+    document.body.appendChild(form);
+    form.submit();
+    document.body.removeChild(form);
