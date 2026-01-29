# ADMIN - VOUCHER

= DISCOUNT VOUCHER

- si admin gagawa ng voucher, set name, type to discount, start and expiration date, required points, discount value.

= BIRTHDAY VOUCHER

- si admin gagawa ng voucher, set name, type to BIRTHDAY VOUCHER, discount value.

= EVENT VOUCHER

- si admin gagawa ng voucher, set name, type to EVENT VOUCHER, start and expiration date, required points, discount value.

= FREE REFILL VOUCHER

- asi admin gagawa ng voucher, set name, type to EVENT VOUCHER, start and expiration date

sa laahat po ng voucher one time used lang ito.
lets say discount voucher 10%,
upon usage hindi na ito pwede gamitin ni user, at hindi niya na ito ma reredeem,
pero pwede ito ma redeem ng ibang user, pero just like we said isang beses lang.

what if need parin magamit ang 10%?, si admin gagawa ulit lang 10% discount voucher, para ma avail ito ulit, mag seset si admin ng data ng voucher as same from the start.

makikita lang ng ni user yung voucher if hindi pa ito expired, at if hindi niya pa ito nagagamit.

sa birthday voucher is lalabas lang ito or gagana if birthday na ni customer,
auto refresh yung bday voucher bawat taon start from jan 1.

sa free refill automatic ito lumalabas if na reach na ni user yung coffee count na 10,
at lalabas ulit ito if ma rereach ulit ni user yung coffee count na 10

# CASHIER - POS

- lets say may oorder at hindi ito member, valid parin ito at papasok ngunit walang account na malalagyan ng points which is ok lang if hindi naman siya member.

-kapag member yung bibili, palaging gamitin ang scan or upload QR para makuha lahat ng details ni customer at mga available na vouchers nito.

-take note, na merong Select Discount kahit hindi pa nag scan si cashier this is for scalability, lets say gusto nyo bigyan ng discount kahit hindi member pwede gamitin ang select discount.
-at kapag si member naman, may lalabas na list na available na mga discounts which is click ang Used if gagamitin ni cashier upon sa request ni customer na gusto niya gamitin.
