self.addEventListener("push",(event) => {
 
    const notif=event.data.json().notification;

    event.waitUntil(self.regisrtration.showNotification(notif.title,{

        body:notif.body,
        icon:"notif.icon",
        data:{
            url:notif.click_action
        }
    }))

})

self.addEventListener("notificationclick",(event) => {

    event.waitUntil(client.openWindow(event.notification.data.url))
})
