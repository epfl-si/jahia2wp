"""Development tools

(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""

import cProfile, pstats, io, timeit
from datetime import timedelta

"""A so-called "context manager" to time a block of code as a whole.

Unlike Profileit() this is a low-cost, low-resolution operation.

Use like this:

  with Timeit() as t:
     ... do something...
     printf("Done after %f", t.elapsed_seconds())

"""
class Timeit:
    def __enter__(self):
        self.start_time = timeit.default_timer()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        pass

    def elapsed_seconds(self):
        return timedelta(seconds=timeit.default_timer() - start_time)

"""A so-called "context manager" to perform detailed profiling.

Use like this:

  with Profileit() as p:
     ... do something...
     printf(p.stats())

"""
class Profileit:
    def __enter__(self):
        self.pr = cProfile.Profile()
        self.pr.enable()
        self.__stopped = False
        return self

    def __stop(self):
        if self.__stopped:
            return
        self.pr.disable()
        self.__stopped = True

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.__stop()

    def stats(self):
        self.__stop()
        s = io.StringIO()
        ps = pstats.Stats(self.pr, stream=s)
        self.pr.print_stats()
        return s.getvalue()
