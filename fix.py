import re
import sys

text = """38:         }
39: 
40:         * {
41:             box-sizing: border-box;
42:             margin: 0;
43:             padding: 0;
44:         }
45: 
46:         /* إعدادات التمرير */
47:         html, body {
48:             overflow-x: hidden;
49:             overflow-y: auto;
50:             -webkit-overflow-scrolling: touch;
51:         }
52: 
53:         body {
54:             font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
55:             background-color: var(--light-bg);
56:             color: #333;
57:             min-height: 100vh;
58:             direction: rtl;
59:         }
60: 
61:         /* Header */
62:         .header {
63:             background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
64:             color: white;
65:             padding: 15px 20px;
66:             padding-top: 45px; /* المسافة الآمنة + الـ padding العادي */
67:             position: fixed;
68:             top: 0;
69:             left: 0;
70:             right: 0;
71:             z-index: 9999;
72:             display: flex;
73:             align-items: center;
74:             gap: 15px;
75:         }
76: 
77:         .header .back-btn {
78:             background: none;
79:             border: none;
80:             color: white;
81:             cursor: pointer;
82:             display: flex;
83:             align-items: center;
84:         }
85: 
86:         .header .back-btn .material-icons {
87:             font-size: 28px;
88:         }
89: 
90:         .header h1 {
91:             font-size: 1.2rem;
92:             font-weight: 600;
93:         }
94: 
95:         /* رسائل الحالة */
96:         .status-message {
97:             padding: 12px 15px;
98:             margin: 15px;
99:             border-radius: 8px;
100:             text-align: center;
101:             font-size: 14px;
102:             display: none;
103:         }
104:         .status-message.info { background: #e3f2fd; color: #1565c0; display: block; }
105:         .status-message.warning { background: #fff3e0; color: #e65100; display: block; }
106:         .status-message.error { background: #ffebee; color: #c62828; display: block; }
107:         .status-message.success { background: #e8f5e9; color: #2e7d32; display: block; }
108: 
109:         /* إحصائيات المزامنة */
110:         .sync-stats {
111:             display: grid;
112:             grid-template-columns: repeat(2, 1fr);
113:             gap: 15px;
114:             padding: 15px;
115:             margin-top: 0px;
116:         }
117: 
118:         .stat-card {
119:             background: white;
120:             border-radius: 12px;
121:             padding: 20px;
122:             text-align: center;
123:             box-shadow: 0 2px 8px rgba(0,0,0,0.08);
124:         }
125: 
126:         .stat-card .icon {
127:             width: 50px;
128:             height: 50px;
129:             margin: 0 auto 10px;
130:             border-radius: 12px;
131:             display: flex;
132:             align-items: center;
133:             justify-content: center;
134:         }
135: 
136:         .stat-card .icon .material-icons {
137:             font-size: 28px;
138:             color: white;
139:         }
140: 
141:         .stat-card.local .icon { background: var(--primary-color); }
142:         .stat-card.pending .icon { background: var(--warning-color); }
143:         .stat-card.synced .icon { background: var(--success-color); }
144:         .stat-card.files .icon { background: var(--info-color); }
145: 
146:         .stat-card .value {
147:             font-size: 2rem;
148:             font-weight: 700;
149:             color: #333;
150:         }
151: 
152:         .stat-card .label {
153:             font-size: 0.85rem;
154:             color: #666;
155:             margin-top: 5px;
156:         }
157: 
158:         /* قسم الجمعيات والحالات */
159:         .section {
160:             background: white;
161:             margin: 15px;
162:             border-radius: 12px;
163:             box-shadow: 0 2px 8px rgba(0,0,0,0.08);
164:             overflow: hidden;
165:         }
166: 
167:         .section-header {
168:             background: var(--light-bg);
169:             padding: 15px;
170:             border-bottom: 1px solid var(--border-color);
171:             display: flex;
172:             justify-content: space-between;
173:             align-items: center;
174:         }
175: 
176:         .section-header h3 {
177:             font-size: 1rem;
178:             color: #333;
179:             display: flex;
180:             align-items: center;
181:             gap: 8px;
182:         }
183: 
184:         .section-header .material-icons {
185:             color: var(--primary-color);
186:         }
187: 
188:         .section-content {
189:             padding: 15px;
190:         }
191: 
192:         .list-item {
193:             display: flex;
194:             justify-content: space-between;
195:             align-items: center;
196:             padding: 12px 0;
197:             border-bottom: 1px solid #f0f0f0;
198:         }
199: 
200:         .list-item:last-child {
201:             border-bottom: none;
202:         }
203: 
204:         .list-item .name {
205:             font-weight: 500;
206:         }
207: 
208:         .list-item .count {
209:             background: var(--primary-color);
210:             color: white;
211:             padding: 4px 12px;
212:             border-radius: 15px;
213:             font-size: 0.8rem;
214:         }
215: 
216:         /* أزرار الإجراءات */
217:         .actions {
218:             padding: 15px;
219:             display: flex;
220:             flex-direction: column;
221:             gap: 10px;
222:         }
223: 
224:         .action-btn {
225:             width: 100%;
226:             padding: 15px;
227:             border: none;
228:             border-radius: 10px;
229:             font-size: 1rem;
230:             font-weight: 600;
231:             cursor: pointer;
232:             display: flex;
233:             align-items: center;
234:             justify-content: center;
235:             gap: 10px;
236:         }
237: 
238:         .action-btn.primary {
239:             background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
240:             color: white;
241:         }
242: 
243:         .action-btn.secondary {
244:             background: #e0e0e0;
245:             color: #333;
246:         }
247: 
248:         .action-btn.danger {
249:             background: var(--danger-color);
250:             color: white;
251:         }
252: 
253:         .action-btn:disabled {
254:             opacity: 0.6;
255:             cursor: not-allowed;
256:         }
257: 
258:         .action-btn .material-icons {
259:             font-size: 22px;
260:         }
261: 
262:         /* أكورديون الإجراءات */
263:         .action-accordion {
264:             background: white;
265:             margin: 10px 15px;
266:             border-radius: 12px;
267:             box-shadow: 0 2px 8px rgba(0,0,0,0.08);
268:             overflow: hidden;
269:         }
270: 
271:         .action-accordion.download {
272:             border-right: 4px solid var(--primary-color);
273:         }
274: 
275:         .action-accordion.upload {
276:             border-right: 4px solid var(--warning-color);
277:         }
278: 
279:         .action-accordion.delete {
280:             border-right: 4px solid var(--danger-color);
281:         }
282: 
283:         .accordion-header {
284:             padding: 15px;
285:             display: flex;
286:             justify-content: space-between;
287:             align-items: center;
288:             cursor: pointer;
289:             user-select: none;
290:             transition: background 0.2s;
291:         }
292: 
293:         .accordion-header:hover {
294:             background: #f5f5f5;
295:         }
296: 
297:         .accordion-header .header-content {
298:             display: flex;
299:             align-items: center;
300:             gap: 12px;
301:         }
302: 
303:         .accordion-header .header-icon {
304:             width: 40px;
305:             height: 40px;
306:             border-radius: 10px;
307:             display: flex;
308:             align-items: center;
309:             justify-content: center;
310:         }
311: 
312:         .action-accordion.download .header-icon {
313:             background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
314:             color: white;
315:         }
316: 
317:         .action-accordion.upload .header-icon {
318:             background: linear-gradient(135deg, #ff9800, #f57c00);
319:             color: white;
320:         }
321: 
322:         .action-accordion.delete .header-icon {
323:             background: linear-gradient(135deg, var(--danger-color), #b71c1c);
324:             color: white;
325:         }
326: 
327:         .accordion-header .header-title {
328:             font-weight: 600;
329:             color: #333;
330:         }
331: 
332:         .accordion-header .header-desc {
333:             font-size: 0.8rem;
334:             color: #888;
335:             margin-top: 2px;
336:         }
337: 
338:         .accordion-header .toggle-icon {
339:             transition: transform 0.3s;
340:             color: #888;
341:         }
342: 
343:         .accordion-header.expanded .toggle-icon {
344:             transform: rotate(180deg);
345:         }
346: 
347:         .accordion-content {
348:             max-height: 0;
349:             overflow: hidden;
350:             transition: max-height 0.3s ease-out;
351:         }
352: 
353:         .accordion-content.expanded {
354:             max-height: 200px;
355:         }
356: 
357:         .accordion-body {
358:             padding: 0 15px 15px;
359:         }
360: 
361:         .accordion-warning {
362:             background: #fff3e0;
363:             padding: 10px;
364:             border-radius: 8px;
365:             margin-bottom: 12px;
366:             font-size: 0.85rem;
367:             color: #e65100;
368:             display: flex;
369:             align-items: center;
370:             gap: 8px;
371:         }
372: 
373:         .accordion-warning.danger {
374:             background: #ffebee;
375:             color: #c62828;
376:         }
377: 
378:         .accordion-action-btn {
379:             width: 100%;
380:             padding: 12px 15px;
381:             border: none;
382:             border-radius: 8px;
383:             font-size: 0.95rem;
384:             font-weight: 600;
385:             cursor: pointer;
386:             display: flex;
387:             align-items: center;
388:             justify-content: center;
389:             gap: 8px;
390:         }
391: 
392:         .accordion-action-btn.primary {
393:             background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
394:             color: white;
395:         }
396: 
397:         .accordion-action-btn.warning {
398:             background: linear-gradient(135deg, #ff9800, #f57c00);
399:             color: white;
400:         }
401: 
402:         .accordion-action-btn.danger {
403:             background: linear-gradient(135deg, var(--danger-color), #b71c1c);
404:             color: white;
405:         }
406: 
407:         .accordion-action-btn:disabled {
408:             opacity: 0.6;
409:             cursor: not-allowed;
410:         }
411: 
412:         /* شريط التقدم */
413:         .progress-section {
414:             padding: 15px;
415:             display: none;
416:         }
417: 
418:         .progress-section.active {
419:             display: block;
420:         }
421: 
422:         .progress-bar {
423:             height: 8px;
424:             background: #e0e0e0;
425:             border-radius: 4px;
426:             overflow: hidden;
427:             margin-bottom: 10px;
428:         }
429: 
430:         .progress-fill {
431:             height: 100%;
432:             background: var(--primary-color);
433:             transition: width 0.3s;
434:         }
435: 
436:         .progress-text {
437:             text-align: center;
438:             font-size: 0.9rem;
439:             color: #666;
440:         }
441: 
442:         /* سجل المزامنة */
443:         .sync-log {
444:             max-height: 200px;
445:             overflow-y: auto;
446:             font-family: monospace;
447:             font-size: 12px;
448:             background: #f5f5f5;
449:             padding: 10px;
450:             border-radius: 8px;
451:             margin-top: 10px;
452:         }
453: 
454:         .sync-log .log-entry {
455:             padding: 3px 0;
456:             border-bottom: 1px solid #e0e0e0;
457:         }
458: 
459:         .sync-log .log-entry:last-child {
460:             border-bottom: none;
461:         }
462: 
463:         .sync-log .log-time {
464:             color: #888;
465:         }
466: 
467:         .sync-log .log-success {
468:             color: var(--success-color);
469:         }
470: 
471:         .sync-log .log-error {
472:             color: var(--danger-color);
473:         }
474: 
475:         /* آخر مزامنة */
476:         .last-sync-info {
477:             text-align: center;
478:             padding: 15px;
479:             color: #666;
480:             font-size: 0.85rem;
481:         }
482: 
483:         /* شريط حالة الشبكة */
484:         .network-status-bar {
485:             margin: 0px 15px 10px;
486:             padding: 12px 15px;
487:             border-radius: 10px;
488:             display: flex;
489:             align-items: center;
490:             gap: 12px;
491:             font-size: 14px;
492:         }
493: 
494:         .network-status-bar.online {
495:             background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
496:             border: 1px solid #a5d6a7;
497:         }
498: 
499:         .network-status-bar.offline {
500:             background: linear-gradient(135deg, #fff3e0, #ffe0b2);
501:             border: 1px solid #ffcc80;
502:         }
503: 
504:         .network-status-bar.uploading {
505:             background: linear-gradient(135deg, #e3f2fd, #bbdefb);
506:             border: 1px solid #90caf9;
507:         }
508: 
509:         .network-status-bar .status-icon {
510:             width: 36px;
511:             height: 36px;
512:             border-radius: 50%;
513:             display: flex;
514:             align-items: center;
515:             justify-content: center;
516:             flex-shrink: 0;
517:         }
518: 
519:         .network-status-bar.online .status-icon {
520:             background: #ffffff;
521:             color: white;
522:         }
523: 
524:         .network-status-bar.offline .status-icon {
525:             background: #ff9800;
526:             color: white;
527:         }
528: 
529:         .network-status-bar.uploading .status-icon {
530:             background: #2196f3;
531:             color: white;
532:             animation: pulse 1.5s infinite;
533:         }
534: 
535:         @keyframes pulse {
536:             0%, 100% { transform: scale(1); opacity: 1; }
537:             50% { transform: scale(1.1); opacity: 0.8; }
538:         }
539: 
540:         .network-status-bar .status-text {
541:             flex: 1;
542:         }
543: 
544:         .network-status-bar .status-title {
545:             font-weight: 600;
546:             color: #333;
547:             font-size: 13px;
548:         }
549: 
550:         .network-status-bar .status-subtitle {
551:             font-size: 11px;
552:             color: #666;
553:         }
554: 
555:         .network-status-bar .queue-badge {
556:             background: #ff9800;
557:             color: white;
558:             padding: 4px 10px;
559:             border-radius: 12px;
560:             font-size: 11px;
561:             font-weight: 600;
562:         }
563:     </style>
564: </head>
565: <body>
566:     <!-- Safe Area Bars -->
567:     <div class="safe-area-top"></div>
568:     <div class="safe-area-bottom"></div>
569: 
570:     <!-- Header -->
571:     <header class="header">
572:         <button class="back-btn" onclick="goBack()">
573:             <span class="material-icons">arrow_forward</span>
574:         </button>
575:         <h1>متابعة المزامنة</h1>
576:     </header>
577: 
578:     <!-- رسالة الحالة -->
579:     <div id="statusMessage" class="status-message"></div>
580: 
581:     <!-- شريط حالة الشبكة والرفع التلقائي -->
582:     <div class="network-status-bar online" id="networkStatusBar">
583:         <div class="status-icon">
584:             <span class="material-icons" id="networkIcon">wifi</span>
585:         </div>
586:         <div class="status-text">
587:             <div class="status-title" id="networkTitle">متصل بالإنترنت</div>
588:             <div class="status-subtitle" id="networkSubtitle">الرفع التلقائي مفعل</div>
589:         </div>
590:         <span class="queue-badge" id="queueBadge" style="display: none;">0 ملف في الطابور</span>
591:     </div>
592: 
593:     <!-- إحصائيات المزامنة الرئيسية -->
594:     <div class="sync-stats">
595:         <div class="stat-card local">
596:             <div class="icon">
597:                 <span class="material-icons">storage</span>
598:             </div>
599:             <div class="value" id="statLocal">0</div>
600:             <div class="label">كفالات محلية</div>
601:         </div>
602:         <div class="stat-card pending" onclick="showPendingDetails()" style="cursor: pointer;">
603:             <div class="icon">
604:                 <span class="material-icons">pending</span>
605:             </div>
606:             <div class="value" id="statPending">0</div>
607:             <div class="label">تحديثات معلقة</div>
608:             <small style="font-size: 10px; color: #856404;">اضغط للتفاصيل</small>
609:         </div>
610:         <div class="stat-card synced">
611:             <div class="icon">
612:                 <span class="material-icons">cloud_done</span>
613:             </div>
614:             <div class="value" id="statSponsors">0</div>
615:             <div class="label">جمعيات</div>
616:         </div>
617:         <div class="stat-card files">
618:             <div class="icon">
619:                 <span class="material-icons">photo_library</span>
620:             </div>
621:             <div class="value" id="statFiles">0</div>
622:             <div class="label">ملفات</div>
623:         </div>
624:     </div>
625: 
626:     <!-- إحصائيات الجداول المرجعية -->
627:     <div class="section" style="margin-top: 15px;">
628:         <div class="section-header">
629:             <h3>
630:                 <span class="material-icons">table_chart</span>
631:                 الجداول المحفوظة محلياً
632:             </h3>
633:         </div>
634:         <div class="section-content">
635:             <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
636:                 <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; text-align: center;">
637:                     <span style="font-size: 24px; font-weight: bold; color: #2e7d32;" id="statSponsorsCount">0</span>
638:                     <div style="font-size: 12px; color: #666;">جمعيات</div>
639:                 </div>
640:                 <div style="background: #e3f2fd; padding: 12px; border-radius: 8px; text-align: center;">
641:                     <span style="font-size: 24px; font-weight: bold; color: #1565c0;" id="statStatusesCount">0</span>
642:                     <div style="font-size: 12px; color: #666;">حالات الكفالة</div>
643:                 </div>
644:                 <div style="background: #fff3e0; padding: 12px; border-radius: 8px; text-align: center;">
645:                     <span style="font-size: 24px; font-weight: bold; color: #ef6c00;" id="statCitiesCount">0</span>
646:                     <div style="font-size: 12px; color: #666;">مدن</div>
647:                 </div>
648:                 <div style="background: #fce4ec; padding: 12px; border-radius: 8px; text-align: center;">
649:                     <span style="font-size: 24px; font-weight: bold; color: #c2185b;" id="statBanksCount">0</span>
650:                     <div style="font-size: 12px; color: #666;">بنوك</div>
651:                 </div>
652:                 <div style="background: #f3e5f5; padding: 12px; border-radius: 8px; text-align: center;">
653:                     <span style="font-size: 24px; font-weight: bold; color: #7b1fa2;" id="statHealthStatusesCount">0</span>
654:                     <div style="font-size: 12px; color: #666;">حالات صحية</div>
655:                 </div>
656:                 <div style="background: #e0f2f1; padding: 12px; border-radius: 8px; text-align: center;">
657:                     <span style="font-size: 24px; font-weight: bold; color: #00695c;" id="statSponsorshipTypesCount">0</span>
658:                     <div style="font-size: 12px; color: #666;">أنواع الكفالة</div>
659:                 </div>
660:             </div>
661:         </div>
662:     </div>
663: 
664:     <!-- آخر مزامنة -->
665:     <div class="last-sync-info" id="lastSyncInfo">
666:         آخر مزامنة: غير متوفر
667:     </div>
668: 
669:     <!-- تحذير تحسين البطارية -->
670:     <div id="batteryWarning" style="display: none; margin: 10px 15px; padding: 12px; background: linear-gradient(135deg, #fff3e0, #ffe0b2); border: 1px solid #ffcc80; border-radius: 10px;">
671:         <div style="display: flex; align-items: center; gap: 10px;">
672:             <span class="material-icons" style="color: #e65100; font-size: 28px;">battery_alert</span>
673:             <div style="flex: 1;">
674:                 <div style="font-weight: 600; color: #e65100;">تحسين البطارية نشط</div>
675:                 <div style="font-size: 12px; color: #666;">قد يتوقف التطبيق عن العمل في الخلفية</div>
676:             </div>
677:             <button onclick="requestBatteryExemption()" style="padding: 8px 12px; background: #ff9800; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600;">
678:                 إصلاح
679:             </button>
680:         </div>
681:     </div>
682: 
683:     <!-- ⚠️ قسم مراقبة خدمة المزامنة في الخلفية -->
684:     <div class="section" style="margin: 10px 15px; padding: 15px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
685:         <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
686:             <span class="material-icons" style="color: var(--primary-color); font-size: 24px;">monitor_heart</span>
687:             <h3 style="font-size: 16px; font-weight: 600; color: #333;">مراقبة خدمة الخلفية</h3>
688:         </div>
689: 
690:         <!-- حالة الخدمة -->
691:         <div id="backgroundServiceStatus" style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f5f5f5; border-radius: 8px; margin-bottom: 10px;">
692:             <div id="serviceStatusDot" style="width: 12px; height: 12px; border-radius: 50%; background: #9e9e9e;"></div>
693:             <div style="flex: 1;">
694:                 <div id="serviceStatusText" style="font-weight: 600; font-size: 13px;">متوقفة</div>
695:                 <div id="serviceStatusDesc" style="font-size: 11px; color: #666;">لا توجد عمليات قيد التنفيذ</div>
696:             </div>
697:         </div>
698: 
699:         <!-- إحصائيات العمليات -->
700:         <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px;">
701:             <div style="text-align: center; padding: 8px; background: #e8f5e9; border-radius: 8px;">
702:                 <div id="completedOpsCount" style="font-size: 18px; font-weight: bold; color: #2e7d32;">0</div>
703:                 <div style="font-size: 10px; color: #666;">عمليات مكتملة</div>
704:             </div>
705:             <div style="text-align: center; padding: 8px; background: #fff3e0; border-radius: 8px;">
706:                 <div id="pendingOpsCount" style="font-size: 18px; font-weight: bold; color: #ef6c00;">0</div>
707:                 <div style="font-size: 10px; color: #666;">في الانتظار</div>
708:             </div>
709:             <div style="text-align: center; padding: 8px; background: #ffebee; border-radius: 8px;">
710:                 <div id="failedOpsCount" style="font-size: 18px; font-weight: bold; color: #c62828;">0</div>
711:                 <div style="font-size: 10px; color: #666;">فشلت</div>
712:             </div>
713:         </div>
714: 
715:         <!-- سجل العمليات الأخيرة -->
716:         <div style="max-height: 120px; overflow-y: auto; background: #fafafa; border-radius: 8px; padding: 8px; font-size: 11px;">
717:             <div style="font-weight: 600; color: #666; margin-bottom: 5px;">آخر العمليات:</div>
718:             <div id="recentOperationsLog" style="color: #555;">
719:                 <div style="padding: 3px 0; border-bottom: 1px solid #eee;">لا توجد عمليات مسجلة</div>
720:             </div>
721:         </div>
722: 
723:         <!-- أزرار التحكم -->
724:         <div style="display: flex; gap: 8px; margin-top: 12px;">
725:             <button id="startServiceBtn" onclick="startBackgroundServiceManually()" style="flex: 1; padding: 10px; background: #4caf50; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px;">
726:                 <span class="material-icons" style="font-size: 16px;">play_arrow</span>
727:                 تشغيل
728:             </button>
729:             <button id="forceStopServiceBtn" onclick="forceStopBackgroundService()" style="flex: 1; padding: 10px; background: #f44336; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px;">
730:                 <span class="material-icons" style="font-size: 16px;">stop</span>
731:                 إيقاف
732:             </button>
733:             <button id="clearLogsBtn" onclick="clearOperationsLog()" style="flex: 1; padding: 10px; background: #607d8b; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px;">
734:                 <span class="material-icons" style="font-size: 16px;">delete_sweep</span>
735:                 مسح
736:             </button>
737:         </div>
738:     </div>
739: 
740:     <!-- شريط التقدم -->
741:     <div class="progress-section" id="progressSection">
742:         <div class="progress-bar">
743:             <div class="progress-fill" id="progressFill" style="width: 0%"></div>
744:         </div>
745:         <div class="progress-text" id="progressText">جاري المزامنة...</div>
746:     </div>
747: 
748:     <!-- أزرار الإجراءات - في أكورديون -->
749: 
750:     <!-- أكورديون تحميل البيانات -->
751:     <div class="action-accordion download">
752:         <div class="accordion-header" onclick="toggleAccordion(this)">
753:             <div class="header-content">
754:                 <div class="header-icon">
755:                     <span class="material-icons">cloud_download</span>
756:                 </div>
757:                 <div>
758:                     <div class="header-title">تحميل من السحابة</div>
759:                     <div class="header-desc">تحديث البيانات المحلية من الخادم</div>
760:                 </div>
761:             </div>
762:             <span class="material-icons toggle-icon">expand_more</span>
763:         </div>
764:         <div class="accordion-content">
765:             <div class="accordion-body">
766:                 <div class="accordion-warning">
767:                     <span class="material-icons" style="font-size: 18px;">info</span>
768:                     سيتم تحديث جميع البيانات من السحابة
769:                 </div>
770:                 <button class="accordion-action-btn primary" id="syncBtn" onclick="performSync()">
771:                     <span class="material-icons">sync</span>
772:                     بدء المزامنة
773:                 </button>
774:             </div>
775:         </div>
776:     </div>
777: 
778:     <!-- أكورديون رفع التحديثات -->
779:     <div class="action-accordion upload">
780:         <div class="accordion-header" onclick="toggleAccordion(this)">
781:             <div class="header-content">
782:                 <div class="header-icon">
783:                     <span class="material-icons">cloud_upload</span>
784:                 </div>
785:                 <div>
786:                     <div class="header-title">رفع التحديثات</div>
787:                     <div class="header-desc">إرسال التعديلات المحلية للسحابة</div>
788:                 </div>
789:             </div>
790:             <span class="material-icons toggle-icon">expand_more</span>
791:         </div>
792:         <div class="accordion-content">
793:             <div class="accordion-body">
794:                 <div class="accordion-warning">
795:                     <span class="material-icons" style="font-size: 18px;">pending_actions</span>
796:                     سيتم رفع جميع التحديثات المعلقة
797:                 </div>
798:                 <button class="accordion-action-btn warning" id="uploadBtn" onclick="uploadPending()">
799:                     <span class="material-icons">backup</span>
800:                     رفع التحديثات للسحابة
801:                 </button>
802:             </div>
803:         </div>
804:     </div>
805: 
806:     <!-- أكورديون مسح البيانات -->
807:     <div class="action-accordion delete">
808:         <div class="accordion-header" onclick="toggleAccordion(this)">
809:             <div class="header-content">
810:                 <div class="header-icon">
811:                     <span class="material-icons">delete_sweep</span>
812:                 </div>
813:                 <div>
814:                     <div class="header-title">مسح البيانات المحلية</div>
815:                     <div class="header-desc">حذف جميع البيانات من الجهاز</div>
816:                 </div>
817:             </div>
818:             <span class="material-icons toggle-icon">expand_more</span>
819:         </div>
820:         <div class="accordion-content">
821:             <div class="accordion-body">
822:                 <div class="accordion-warning danger">
823:                     <span class="material-icons" style="font-size: 18px;">warning</span>
824:                     تحذير: هذا الإجراء لا يمكن التراجع عنه!
825:                 </div>
826:                 <button class="accordion-action-btn danger" id="clearBtn" onclick="requestClearLocalData()">
827:                     <span class="material-icons">delete_forever</span>
828:                     مسح جميع البيانات
829:                 </button>
830:             </div>
831:         </div>
832:     </div>
833: 
834:     <!-- نافذة كلمة المرور -->
835:     <div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
836:         <div style="background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 350px; text-align: center;">
837:             <h3 style="margin: 0 0 15px; color: #dc3545;">⚠️ تأكيد الحذف</h3>
838:             <p style="color: #666; margin-bottom: 20px;">هذا الإجراء سيحذف جميع البيانات المحلية. أدخل كلمة المرور للمتابعة:</p>
839:             <input type="password" id="deletePassword" placeholder="كلمة المرور" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; margin-bottom: 15px;">
840:             <div style="display: flex; gap: 10px;">
841:                 <button onclick="cancelClearLocalData()" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 14px;">إلغاء</button>
842:                 <button onclick="confirmClearLocalData()" style="flex: 1; padding: 12px; background: #dc3545; color: white; border: none; border-radius: 8px; font-size: 14px;">تأكيد الحذف</button>
843:             </div>
844:         </div>
845:     </div>
846: 
847:     <!-- نافذة التحديثات المعلقة -->
848:     <div id="pendingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
849:         <div style="background: white; border-radius: 12px; width: 95%; max-width: 500px; max-height: 80vh; overflow: hidden; display: flex; flex-direction: column;">
850:             <div style="background: #ff9800; color: white; padding: 15px; display: flex; align-items: center; justify-content: space-between;">
851:                 <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
852:                     <span class="material-icons">pending_actions</span>
853:                     التحديثات المعلقة
854:                 </h3>
855:                 <button onclick="closePendingModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
856:             </div>
857:             <div id="pendingList" style="padding: 15px; overflow-y: auto; flex: 1;">
858:                 <p style="text-align: center; color: #888;">جاري التحميل...</p>
859:             </div>
860:             <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px;">
861:                 <button onclick="closePendingModal()" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 14px;">إغلاق</button>
862:                 <button onclick="uploadPendingFromModal()" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 14px;">
863:                     <span class="material-icons" style="vertical-align: middle; font-size: 18px;">cloud_upload</span>
864:                     رفع الكل
865:                 </button>
866:             </div>
867:         </div>
868:     </div>
869: 
870:     <!-- سجل المزامنة -->
871:     <div class="section">
872:         <div class="section-header">
873:             <h3>
874:                 <span class="material-icons">history</span>
875:                 سجل المزامنة
876:             </h3>
877:         </div>
878:         <div class="sync-log" id="syncLog">
879:             <div class="log-entry">
880:                 <span class="log-time">[--:--:--]</span>
881:                 لم تتم أي عمليات مزامنة بعد
882:             </div>
883:         </div>
884:     </div>
885: 
886:     <!-- خدمة المزامنة -->
887:     <script src="js/sync-service-real.js?v=20260122-production"></script>
888: """
clean_text = re.sub(r'^\d+:\s?', '', text, flags=re.MULTILINE)

filepath = 'android/app/src/main/assets/public/sync-monitor.html'
try:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
except Exception as e:
    print(f"Error reading file: {e}")
    sys.exit(1)

# The corrupted file has lines 1-37 intact.
# Line 37 is:            --border-color: #dee2e6;\n        }\n
split_point1 = '            --border-color: #dee2e6;\n        }\n'
idx1 = content.find(split_point1)
if idx1 == -1:
    print("Could not find split point 1")
    sys.exit(1)

top_part = content[:idx1 + len(split_point1)]

# The rest of the file starts at:        function toggleAccordion(header) {
split_point2 = '        function toggleAccordion(header) {'
idx2 = content.find(split_point2)
if idx2 == -1:
    print("Could not find split point 2")
    sys.exit(1)

bottom_part = content[idx2:]

middle_glue = '''
    <script>
        // ========================================
        // دالة تبديل الأكورديون
        // ========================================
'''

new_content = top_part + clean_text + middle_glue + bottom_part

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(new_content)

print("File restored successfully.")
