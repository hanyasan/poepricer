export interface MetricsTerm {
  title: string
  description: string
}

export interface MetricsBlock {
  id: string
  title: string
  paragraphs: string[]
  terms?: MetricsTerm[]
  list?: string[]
}

/**
 * The texts of the page about the metrics. They are long and structured, so they live
 * here per language instead of the message files.
 */
export const metricsBlocks: Record<'ru' | 'en', MetricsBlock[]> = {
  ru: [
    {
      id: 'source',
      title: 'Откуда берутся данные',
      paragraphs: [
        'Цены и список лиг мы получаем с poe.ninja. Каждые несколько минут сайт сохраняет «снимок» цен лиги, а из этих снимков строятся все показатели на странице',
        'Лига попадает в метрики не автоматически: её нужно добавить отдельно. Лиги без метрик на сайте не отображаются',
        'Цены в метриках выражены в определённой валюте, и для каждой валюты считается свой набор метрик. Список валют берётся из базовых валют poe.ninja, а некоторые валюты для метрик могут быть добавлены отдельно, например Mirror of Kalandra. Выбрать валюту можно в шапке страницы лиги',
      ],
    },
    {
      id: 'basket',
      title: 'Корзина предметов',
      paragraphs: [
        'Сутки делятся на несколько отрезков по несколько часов (границы отрезков по UTC). Такой отрезок вместе с собранными в нём данными называем корзиной. В корзину попадают снимки цен всей лиги за этот промежуток, а из них для каждого предмета берётся одна медианная цена. Медиана не даёт случайному скачку в одном снимке исказить картину',
        'Правила попадания в корзину:',
      ],
      list: [
        'Предмет входит в корзину, если его цена не ниже порога. Порог свой для каждой валюты: так дешёвые и шумные предметы не влияют на оценку рынка',
        'Если предмет уже был в корзине в прошлый раз, он остаётся в ней, даже если подешевел ниже порога. Иначе он пропал бы из расчёта как раз тогда, когда его цена сильно двигается',
        'Если в корзине у предмета вообще нет цены, он из неё выпадает',
        'Количество сделок на попадание в корзину не влияет: самые дорогие предметы торгуются редко, и отбор по обороту почти опустошил бы корзину. Малый оборот учитывается позже, при расчёте дельты цены',
      ],
    },
    {
      id: 'market',
      title: 'Метрики рынка',
      paragraphs: ['Рынок мы оцениваем по движению предметов из корзины. Для каждой корзины считаются три числа'],
      terms: [
        {
          title: 'Дельта цены рынка',
          description:
            'Насколько в среднем изменились цены по сравнению с предыдущей корзиной. Берётся медиана изменений всех предметов, а не среднее, чтобы несколько резких скачков не перекосили результат. Предметы с малым числом сделок учитываются с меньшим весом или не учитываются вовсе. Значение 0,05 примерно означает рост цен на 5%',
        },
        {
          title: 'Предметов в расчёте',
          description: 'Сколько предметов реально участвовало в расчёте дельты цены рынка',
        },
        {
          title: 'Предметов в корзине',
          description:
            'Сколько предметов в корзине всего, включая тех, у кого нет изменения цены или слишком мало сделок. Чем больше доля предметов в расчёте, тем надёжнее оценка рынка. Если она мала, оценка может сильно «шуметь»',
        },
      ],
      list: [
        'Резкая дельта цены рынка означает «рыночный день»: сдвинулось почти всё сразу, например скачок цены самой валюты. В такие дни выводы по отдельным предметам ненадёжны',
      ],
    },
    {
      id: 'items',
      title: 'Метрики предметов',
      paragraphs: ['Для каждого предмета из корзины мы считаем следующее'],
      terms: [
        {
          title: 'Цена',
          description: 'Медианная цена предмета в корзине, в выбранной валюте',
        },
        {
          title: 'Изменение цены / Δ цены',
          description:
            'Насколько цена изменилась по сравнению с предыдущей корзиной. Считается через логарифм, но для небольших движений это просто доля: 0,05 примерно означает +5%, −0,05 примерно −5%. Если в прошлой корзине у предмета не было цены, показателя нет',
        },
        {
          title: 'Сделок в час / Сделок/ч',
          description:
            'Сколько единиц предмета в среднем продавалось за час. Чем меньше сделок, тем легче случайной сделке сдвинуть цену, и тем меньше доверия к изменению цены. Для предметов, которым мы верим не полностью, значение показано серым',
        },
        {
          title: 'Доверие к изменению цены',
          description:
            'Число от 0 до 1. Если сделок меньше 2 в час, изменение цены мы не учитываем совсем. Начиная с 20 сделок в час доверяем полностью, а между этими значениями доверие растёт постепенно. Предметы без доверия не участвуют в расчёте рынка и беты',
        },
        {
          title: 'Остаток',
          description:
            'Главный показатель. Это та часть изменения цены, которую не объясняет движение рынка: из изменения цены предмета вычитается изменение рынка, умноженное на бету предмета. Показывает, двигается ли предмет сам по себе, а не вместе со всеми. Остаток нужно сравнивать с обычным для этого предмета колебанием, а не с одним процентом для всех',
        },
        {
          title: 'Обычное колебание остатка / Колебание',
          description:
            'Насколько остаток предмета обычно отклоняется от нуля за последние 7 суток. Считается так, чтобы единичные выбросы почти не влияли. Если остаток заметно больше обычного колебания (ориентир: в два раза), движение считается необычным и попадает в соответствующий список',
        },
        {
          title: 'Остаток относительно колебания / Кратность',
          description:
            'Во сколько раз остаток предмета больше его обычного колебания. Значение 2 и выше означает необычное движение, знак показывает направление: плюс — цена выросла сильнее, чем объясняет рынок, минус — упала сильнее',
        },
        {
          title: 'Число наблюдений / Выборка',
          description:
            'Сколько значений использовано, чтобы оценить обычное колебание. Окно рассчитано на 14 корзин (7 суток): чем ближе к 14, тем надёжнее оценка. Если наблюдений мало, у предмета стоит пометка «мало данных»',
        },
      ],
    },
    {
      id: 'beta',
      title: 'Бета и R²',
      paragraphs: [
        'Эти показатели считаются по истории предмета и пересчитываются раз в сутки по данным за последние 7 суток. Старые значения не затираются, поэтому на странице предмета видно, как менялась бета',
      ],
      terms: [
        {
          title: 'Бета (β)',
          description:
            'Показывает, как сильно предмет реагирует на движение рынка. β = 1: предмет движется вместе с рынком. β = 1,5: когда рынок растёт на 10%, предмет растёт примерно на 15%. β около 0: предмет рынку почти не подчиняется. Пока данных мало, бета подтягивается к 1, чтобы короткая история не дала случайное число. Именно это значение используется в расчёте остатка',
        },
        {
          title: 'Сырая бета',
          description:
            'Та же чувствительность к рынку, но до подтягивания к 1. Считается как медиана наклонов между всеми парами корзин окна (оценка Тейла–Сена), поэтому одиночный выброс её не сдвигает. В расчёт остатка она не идёт: при короткой истории она слишком шумная. Смотрите на неё как на проверку: чем сильнее она отличается от обычной беты, тем меньше наблюдений, а значит, тем меньше можно доверять обеим цифрам. Когда данных хватает, они почти совпадают',
        },
        {
          title: 'R²',
          description:
            'Квадрат корреляции Пирсона между доходностью предмета и рынка, от 0 до 1. Показывает, какая доля колебаний предмета объясняется движением рынка. Высокое значение: предмет следует за рынком, и его остаток небольшой. Значение около 0: предмет живёт сам по себе, и оценивать его нужно по собственному движению',
        },
      ],
    },
    {
      id: 'usage',
      title: 'Как этим пользоваться',
      paragraphs: ['Это ориентиры, а не готовые торговые сигналы'],
      list: [
        'Смотрите на остаток, а не на простое изменение цены: так собственное движение предмета отделяется от общего',
        'Сравнивайте остаток с обычным колебанием именно этого предмета. У распространённых предметов типичное отклонение около 2%, у редких в разы больше',
        'Подтверждайте сдвиг несколькими корзинами подряд (2–3)',
        'Меньше доверяйте выводам в «рыночные дни» и когда в расчёте рынка мало предметов',
        'Не опирайтесь на оценки с пометкой «мало данных»',
        'У предметов с R² около нуля рынок почти ничего не объясняет, их нужно оценивать только по собственному колебанию',
      ],
    },
  ],
  en: [
    {
      id: 'source',
      title: 'Where the data comes from',
      paragraphs: [
        'We get the prices and the list of leagues from poe.ninja. Every few minutes the site saves a “snapshot” of the prices of a league, and all the figures on the page are built from these snapshots',
        'A league does not get into the metrics automatically: it has to be added separately. Leagues without metrics are not shown on the site',
        'The prices in the metrics are expressed in a certain currency, and every currency has its own set of metrics. The list of currencies comes from the base currencies of poe.ninja, and some currencies for the metrics can be added separately, for example Mirror of Kalandra. You can pick the currency in the header of the league page',
      ],
    },
    {
      id: 'basket',
      title: 'The item basket',
      paragraphs: [
        'A day is divided into several slices of a few hours each (the borders of the slices are in UTC). We call such a slice together with the data collected in it a bucket. The price snapshots of the whole league over this period go into the bucket, and one median price is taken for every item from them. The median keeps a random spike in a single snapshot from distorting the picture',
        'Rules for getting into the basket:',
      ],
      list: [
        'An item is in the basket if its price is not below the threshold. The threshold is its own for every currency: this way cheap and noisy items do not affect the estimate of the market',
        'If an item was in the basket last time, it stays there even if it got cheaper than the threshold. Otherwise it would drop out of the calculation exactly when its price moves a lot',
        'If an item has no price in the bucket at all, it drops out of the basket',
        'The number of trades does not affect getting into the basket: the most expensive items are traded rarely, and a selection by turnover would almost empty the basket. A low turnover is taken into account later, when the price delta is calculated',
      ],
    },
    {
      id: 'market',
      title: 'Market metrics',
      paragraphs: ['We estimate the market by the movement of the items of the basket. Three numbers are calculated for every bucket'],
      terms: [
        {
          title: 'Market price delta',
          description:
            'How much the prices changed on average compared with the previous bucket. The median of the changes of all items is taken, not the mean, so that a few sharp spikes do not skew the result. Items with few trades count with a lower weight or do not count at all. A value of 0.05 roughly means that prices rose by 5%',
        },
        {
          title: 'Items in the calculation',
          description: 'How many items actually took part in the calculation of the market price delta',
        },
        {
          title: 'Items in the basket',
          description:
            'How many items there are in the basket in total, including those that have no price change or too few trades. The larger the share of the items in the calculation, the more reliable the estimate of the market. If it is small, the estimate can be very “noisy”',
        },
      ],
      list: [
        'A sharp market price delta means a “market day”: almost everything moved at once, for example a jump in the price of the currency itself. On such days the conclusions about single items are unreliable',
      ],
    },
    {
      id: 'items',
      title: 'Item metrics',
      paragraphs: ['For every item of the basket we calculate the following'],
      terms: [
        {
          title: 'Price',
          description: 'The median price of the item in the bucket, in the selected currency',
        },
        {
          title: 'Price change / Δ price',
          description:
            'How much the price changed compared with the previous bucket. It is calculated through a logarithm, but for small moves it is simply a fraction: 0.05 roughly means +5%, −0.05 roughly −5%. If the item had no price in the previous bucket, there is no figure',
        },
        {
          title: 'Trades per hour / Trades/h',
          description:
            'How many units of the item were sold per hour on average. The fewer the trades, the easier it is for a random trade to move the price, and the less we trust the price change. For items we do not trust fully, the value is shown in gray',
        },
        {
          title: 'Trust in the price change',
          description:
            'A number from 0 to 1. If there are fewer than 2 trades per hour, we do not take the price change into account at all. From 20 trades per hour we trust it fully, and between these values the trust grows gradually. Items without trust do not take part in the calculation of the market and of the beta',
        },
        {
          title: 'Residual',
          description:
            'The main figure. It is the part of the price change that the movement of the market does not explain: the change of the market multiplied by the beta of the item is subtracted from the price change of the item. It shows whether the item moves on its own rather than with everyone else. The residual has to be compared with the usual fluctuation of this item, not with one percent for all',
        },
        {
          title: 'Usual fluctuation of the residual / Fluctuation',
          description:
            'How far the residual of the item usually deviates from zero over the last 7 days. It is calculated so that single outliers hardly affect it. If the residual is clearly larger than the usual fluctuation (a rule of thumb: twice as large), the move is considered unusual and gets into the corresponding list',
        },
        {
          title: 'Residual relative to the fluctuation / Multiple',
          description:
            'How many times the residual of the item is larger than its usual fluctuation. A value of 2 or higher means an unusual move, the sign shows the direction: plus means the price rose more than the market explains, minus means it fell more',
        },
        {
          title: 'Number of observations / Sample',
          description:
            'How many values were used to estimate the usual fluctuation. The window is designed for 14 buckets (7 days): the closer to 14, the more reliable the estimate. If there are few observations, the item is marked “low data”',
        },
      ],
    },
    {
      id: 'beta',
      title: 'Beta and R²',
      paragraphs: [
        'These figures are calculated from the history of the item and are recalculated once a day from the data of the last 7 days. The old values are not overwritten, so the page of the item shows how the beta changed',
      ],
      terms: [
        {
          title: 'Beta (β)',
          description:
            'Shows how strongly the item reacts to the movement of the market. β = 1: the item moves together with the market. β = 1.5: when the market rises by 10%, the item rises by about 15%. β close to 0: the item hardly follows the market. While there is little data, the beta is pulled towards 1 so that a short history does not give a random number. This is the value used in the calculation of the residual',
        },
        {
          title: 'Raw beta',
          description:
            'The same sensitivity to the market, but before the pull towards 1. It is the median of the slopes between all pairs of buckets in the window (the Theil–Sen estimate), so a single outlier does not move it. It does not enter the calculation of the residual: with a short history it is too noisy. Treat it as a check: the further it is from the ordinary beta, the fewer observations there are, and the less both figures can be trusted. When there is enough data, the two are nearly the same',
        },
        {
          title: 'R²',
          description:
            'The squared Pearson correlation between the return of the item and of the market, from 0 to 1. It shows what part of the fluctuations of the item the movement of the market explains. A high value: the item follows the market and its residual is small. A value close to 0: the item lives on its own and has to be judged by its own movement',
        },
      ],
    },
    {
      id: 'usage',
      title: 'How to use this',
      paragraphs: ['These are guidelines, not ready trading signals'],
      list: [
        'Look at the residual, not at the plain price change: this separates the own movement of an item from the general one',
        'Compare the residual with the usual fluctuation of this very item. For common items the typical deviation is about 2%, for rare ones it is several times larger',
        'Confirm a shift with several buckets in a row (2–3)',
        'Trust the conclusions less on “market days” and when the market calculation has few items',
        'Do not rely on estimates marked “low data”',
        'For items with R² close to zero the market explains almost nothing, so judge them only by their own fluctuation',
      ],
    },
  ],
}
