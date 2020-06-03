# Bitrix24HelpDesk
 Bitrix24 Extended HelpDesk
 
This extension of the technical support module creates a page for administering tickets in the public part of Bitrix24.

When creating a ticket from an email, a task is created based on this ticket. The task is tied to the CRM Contact from the email of which the ticket was created. 

If there is no contact yet, a new contact is created.

When closing a ticket, the task closes.

When the task is closed, the ticket closes.

For the solution to work, you need to configure the system mailbox in the same way as for the standard module of the support board: https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=41&LESSON_ID=2622&LESSON_PATH=3911.4557.2622

In addition, you must create a list in which the connection between the ticket, contact, and task will be stored with the following fields:
TICKETID
TASKID
RESPONSIBLE
CONTACTID

And User Fields:

SUPPORT	UF_TICKET_CLOSE_TASK	Да/Нет	100
TASKS_TASK	UF_TICKET_LINK	Ссылка	100
TASKS_TASK	UF_TASK_CLOSE_TICKET	Да/Нет	100


In the init file, you need to replace the list ID with your list ID.

If your system has custom required fields for the task or for contact, you need to either make them optional or edit the init file.
 
 https://bedrosova.blogspot.com/2015/10/24.html
